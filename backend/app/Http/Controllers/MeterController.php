<?php

namespace App\Http\Controllers;

use App\Events\NewLogEvent;
use App\Http\Requests\MeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Http\Resources\ApiResource;
use App\Models\Meter\Meter;
use App\Services\MeterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Controllers\PersonController;
use App\Http\Requests\MeterTypeCreateRequest;
use App\Http\Requests\PersonRequest;
use App\Models\Person\Person;
use App\Services\AddressesService;
use App\Services\AddressGeographicalInformationService;
use App\Services\GeographicalInformationService;
use App\Services\PersonService;
use MPM\Device\DeviceAddressService;
use MPM\Device\DeviceService;
use MPM\Meter\MeterDeviceService;

class MeterController extends Controller
{
    public function __construct(
        private PersonService $personService,
        private MeterService $meterService,
        private DeviceService $deviceService,
        private MeterDeviceService $meterDeviceService,
        private AddressesService $addressService,
        private DeviceAddressService $deviceAddressService,
        private GeographicalInformationService $geographicalInformationService,
        private AddressGeographicalInformationService $addressGeographicalInformationService,
    ) {}

    /**
     * List
     * Lists all used meters with meterType
     * The response is paginated with 15 results on each page/request.
     *
     * @urlParam     page int
     * @urlParam     in_use int to list wether used or all meters
     *
     * @responseFile responses/meters/meters.list.json
     */
    public function index(Request $request): ApiResource
    {
        $inUse = $request->input('in_use');
        $limit = $request->input('limit', config('settings.paginate'));

        return ApiResource::make($this->meterService->getAll($limit, $inUse));
    }

    /**
     * Create
     * Stores a new meter.
     *
     * @bodyParam serial_number string required
     * @bodyParam meter_type_id int required
     * @bodyParam manufacturer_id int required
     *
     * @return mixed
     *
     * @throws ValidationException
     */
    public function store(MeterRequest $request)
    {
        $meterData = (array) $request->all();

        return ApiResource::make($this->meterService->create($meterData));
    }

    public function importFromCsv(Request $request): ApiResource
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls'
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        // Parse file
        if (in_array($ext, ['csv', 'txt'])) {
            $rows = array_map('str_getcsv', file($file->getRealPath()));
        } else {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
        }

        if (empty($rows)) {
            return ApiResource::make([
                'message' => 'No data found',
                'data' => []
            ]);
        }

        // Remove metadata rows with only 1 non-null cell
        $rows = array_filter($rows, fn($r) => count(array_filter($r, fn($v) => $v !== null && $v !== '')) > 1);

        if (empty($rows)) {
            return ApiResource::make([
                'message' => 'No valid data rows',
                'data' => []
            ]);
        }

        // HEADER
        $header = array_map('trim', array_shift($rows));

        // auto-generate header if empty
        if (empty(array_filter($header))) {
            $header = array_map(fn($i) => 'col_' . ($i + 1), array_keys($header));
        }

        // Map rows to associative arrays
        $data = array_map(fn($r) => @array_combine($header, $r), $rows);

        $parsed = [];

        // Helper to calculate max current
        $calcMaxCurrent = function ($unit, $phase = 1) {
            $unit = floatval(preg_replace('/[^0-9.]/', '', $unit));
            if ($phase == 1) {
                $amp = ($unit * 1000) / 230;
            } else {
                $amp = ($unit * 1000) / (400 * sqrt(3));
            }
            return round($amp, 2);
        };

        foreach ($data as $row) {
            $phase = 1;
            $meterTypeId = null;
            $meterId = null;
            $peopleId = null;

            // ===== Create Customer =====
            try {
                $peopleData = [
                    'title'        => $row['Title'] ?? null,
                    'name'         => $row['Customer Name'] ?? null,
                    'surname'      => $row['Surname'] ?? 'N/A',
                    'birth_date'   => $row['Birth Date'] ?? null,
                    'sex'          => $row['Sex'] ?? null,
                    'education'    => $row['Education'] ?? null,
                    'city_id'      => $row['City ID'] ?? 1,
                    'street'       => $row['Street'] ?? null,
                    'email'        => $row['Email'] ?? null,
                    'phone'        => $row['Phone'] ?? null,
                    'country_code' => $row['Country Code'] ?? null,
                    'customer_type' => 1,
                ];
                $peopleRequest = PersonRequest::create('/fake-url', 'POST', $peopleData);
                $peopleController = app(\App\Http\Controllers\PersonController::class);
                $peopleResponse = $peopleController->store($peopleRequest);

                // Resolve the response data
                $responseData = $peopleResponse->getData(true)['data'] ?? null;

                if (!$responseData || !isset($responseData['id'])) {
                    throw new \Exception('Failed to create Person via controller');
                }

                // Load the actual Person model from DB
                $peopleId = $responseData['id'];
                $people = \App\Models\Person\Person::find($peopleId);

                if (!$people) {
                    throw new \Exception("Person with ID $peopleId not found after creation");
                }
            } catch (\Exception $e) {
                $people = [
                    'error' => $e->getMessage(),
                    'data_attempted' => $peopleData ?? null,
                ];
            }

            // ===== Create / Get MeterType =====
            try {
                $maxCurrent = $calcMaxCurrent($row['Total Unit'] ?? null, $phase);

                $existingMeterType = \App\Models\Meter\MeterType::where('max_current', $maxCurrent)
                    ->where('phase', $phase)
                    ->where('online', 1)
                    ->first();

                if ($existingMeterType) {
                    $meterType = $existingMeterType->toArray();
                    $meterTypeId = $existingMeterType->id;
                } else {
                    $meterTypeData = [
                        'max_current' => $maxCurrent,
                        'phase'       => $phase,
                        'online'      => 1,
                    ];
                    $meterTypeRequest = MeterTypeCreateRequest::create('/fake-url', 'POST', $meterTypeData);
                    $meterTypeController = app(\App\Http\Controllers\MeterTypeController::class);
                    $meterTypeResponse = $meterTypeController->store($meterTypeRequest);

                    // Resolve response
                    $responseData = $meterTypeResponse->resolve() ?? null;

                    if (!$responseData || !isset($responseData['id'])) {
                        throw new \Exception('Failed to create MeterType via controller');
                    }

                    // Load the actual MeterType object from DB
                    $meterTypeId = $responseData['id'];
                    $meterType = \App\Models\Meter\MeterType::find($meterTypeId);

                    if (!$meterType) {
                        throw new \Exception("MeterType with ID $meterTypeId not found after creation");
                    }
                }
            } catch (\Exception $e) {
                $meterType = [
                    'error' => $e->getMessage(),
                    'data_attempted' => $meterTypeData ?? null,
                ];
            }

            // ===== Create / Get Meter =====
            try {
                $serialNumber = $row['Meter No.'] ?? null;
                $existingMeter = $this->meterService->getBySerialNumber($serialNumber);

                if ($existingMeter) {
                    $meter = $existingMeter->toArray();
                    $meterId = $existingMeter->id;
                } else {
                    $meterData = [
                        'serial_number'       => $serialNumber,
                        'manufacturer_id'     => 1,
                        'meter_type_id'       => $meterTypeId,
                        'in_use'              => 1,
                        'connection_type_id'  => 1,
                        'connection_group_id' => 1,
                        'tariff_id'           => 1,
                    ];
                    $meterRequest = MeterRequest::create('/fake-url', 'POST', $meterData);
                    $meterController = app(\App\Http\Controllers\MeterController::class);
                    $meterResponse = $meterController->store($meterRequest);
                    // Resolve the response
                    $responseData = $meterResponse->resolve() ?? null;

                    if (!$responseData || !isset($responseData['id'])) {
                        throw new \Exception('Failed to create Meter via controller');
                    }

                    // Load the Meter object from the DB using the returned ID
                    $meterId = $responseData['id'];
                    $meter = Meter::find($meterId);

                    if (!$meter) {
                        throw new \Exception("Meter with ID $meterId not found after creation");
                    }
                }

                $device = $this->deviceService->make([
                    'person_id' => $peopleId,
                    'device_serial' => $serialNumber,
                ]);
                $this->meterDeviceService->setAssigned($device);
                $this->meterDeviceService->setAssignee($meter);
                $this->meterDeviceService->assign();
                $this->deviceService->save($device);
            } catch (\Exception $e) {
                $meter = [
                    'error' => $e->getMessage(),
                    'data_attempted' => $meterData ?? null,
                ];
            }

            // ===== Vending / Transaction =====
            $vend = [
                'price'      => preg_replace('/[^0-9.]/', '', $row['Price'] ?? null),
                'tax'        => preg_replace('/[^0-9.]/', '', $row['Tax'] ?? null),
                'unit'       => preg_replace('/[^0-9.]/', '', $row['Total Unit'] ?? null),
                'total_paid' => preg_replace('/[^0-9.]/', '', $row['Total Paid'] ?? null),
                'token'      => $row['Token'] ?? null,
                'date'       => isset($row['Create Date']) ? date('Y-m-d H:i:s', strtotime($row['Create Date'])) : null,
                'operator'   => $row['Operator'] ?? null,
            ];

            $parsed[] = compact('meterType', 'meter', 'people', 'vend');
        }

        return ApiResource::make([
            'message'    => 'Parsed successfully',
            'columns'    => $header,
            'total_rows' => count($parsed),
            'preview'    => array_slice($parsed, 0, 25),
        ]);
    }

    /**
     * Detail
     * Detailed meter with following relations
     * - MeterTariff.tariff
     * - Meter Type
     * - Meter.connectionType
     * - Meter.connectionGroup
     * - Manufacturer.
     *
     * @urlParam serialNumber string
     *
     * @responseFile responses/meters/meter.detail.json
     */
    public function show(string $serialNumber): ApiResource
    {
        return ApiResource::make($this->meterService->getBySerialNumber($serialNumber));
    }

    /**
     * Search
     * The search term will be searched in following fields
     * - Tariff.name
     * - Serial number.
     *
     * @bodyParam term string required
     *
     * @responseFile responses/meters/meters.search.json
     */
    public function search(): ApiResource
    {
        $term = request('term');
        $paginate = request('paginate') ?? 1;

        return ApiResource::make($this->meterService->search($term, $paginate));
    }

    /**
     * Delete
     * Deletes the meter with its all releations.
     *
     * @urlParam meterId. The ID of the meter to be delete
     */
    public function destroy(int $meterId): JsonResponse
    {
        $this->meterService->getById($meterId);

        return response()->json(null, 204);
    }

    public function update(UpdateMeterRequest $request, Meter $meter): ApiResource
    {
        $creatorId = auth('api')->user()->id;
        $previousDataOfMeter = json_encode($meter->toArray());
        $updatedMeter = $this->meterService->update($meter, $request->validated());
        $updatedDataOfMeter = json_encode($updatedMeter->toArray());
        event(new NewLogEvent([
            'user_id' => $creatorId,
            'affected' => $meter,
            'action' => "Meter infos updated from: $previousDataOfMeter to $updatedDataOfMeter",
        ]));

        return ApiResource::make($updatedMeter);
    }

    public function showConnectionTypes(): ApiResource
    {
        return ApiResource::make($this->meterService->getNumberOfConnectionTypes());
    }
}
