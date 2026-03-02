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
use Illuminate\Support\Facades\Http;

class MeterController extends Controller
{
    public function __construct(
        private MeterService $meterService,
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

    /**
     * Detail
     * Detailed meter with following relations
     * - Tariff.tariff
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

    public function getExternalPortalData(Request $request, string $serialNumber)
    {
        // Get meter
        $meter = $this->meterService->getBySerialNumber($serialNumber);

        if (!$meter) {
            return response()->json([
                'success' => false,
                'message' => 'Meter not found.'
            ], 404);
        }

        // Validate request
        $request->validate([
            'type' => 'required|in:monthly,daily,hourly',
            'Year' => 'required|integer|min:2000',
            'Month' => 'required|integer|between:1,12',
            'Day' => 'required_if:type,hourly|integer|between:1,31'
        ]);

        $type = $request->type;

        // Base URL (better to move to .env)
        $baseUrl = 'http://47.90.189.157:6001/api';

        switch ($type) {

            case 'monthly':
                $endpoint = '/COMM_MonthlyData';
                $queryList = [[
                    "MeterNo" => $meter->serial_number,
                    "Year" => $request->Year,
                    "Month" => $request->Month,
                ]];
                break;

            case 'daily':
                $endpoint = '/COMM_DailyData';
                $queryList = [[
                    "MeterNo" => $meter->serial_number,
                    "Year" => $request->Year,
                    "Month" => $request->Month,
                ]];
                break;

            case 'hourly':
                $endpoint = '/COMM_HourlyData';
                $queryList = [[
                    "MeterNo" => $meter->serial_number,
                    "Year" => $request->Year,
                    "Month" => $request->Month,
                    "Day" => $request->Day,
                ]];
                break;
        }

        try {

            $response = Http::timeout(30)
                ->acceptJson()
                ->post($baseUrl . $endpoint, [
                    "CompanyName" => 'Sandstream',
                    "UserName" => 'pos1',
                    "Password" => 'Sandstream@@25',
                    "QueryList" => $queryList
                ]);

            return response()->json([
                'success' => $response->successful(),
                'data' => $response->json()
            ], $response->status());
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'External portal connection failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
