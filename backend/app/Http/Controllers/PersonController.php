<?php

namespace App\Http\Controllers;

use App\Http\Requests\CityRequest;
use App\Http\Requests\PersonRequest;
use App\Http\Resources\ApiResource;
use App\Models\Country;
use App\Services\AddressesService;
use App\Services\CountryService;
use App\Services\PersonAddressService;
use App\Services\PersonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Requests\MeterTypeCreateRequest;
use App\Services\CityService;

/**
 * Class PersonController.
 *
 * @group People
 */
class PersonController extends Controller
{
    public function __construct(
        private AddressesService $addressService,
        private PersonService $personService,
        private PersonAddressService $personAddressService,
        private CountryService $countryService,
        private CityService $cityService,
    ) {}

    /**
     * List customer/other
     * [ To get a list of registered customers or non-customer like contact person of Meter Manufacturer. ].
     *
     * @urlParam is_customer int optinal. To get a list of customers or non customer. Default : 1
     * @urlParam agent_id int optional. To gget a list of customers of a specific agent.
     * @urlParam limit int optional. The number of items per page.
     * @urlParam active_customer int optional. To get a list of active customers. Default: 0
     *
     * @responseFile responses/people/people.list.json
     */
    public function index(Request $request): ApiResource
    {
        $customerType = $request->input('is_customer', 1);
        $perPage = $request->input('per_page', config('settings.paginate'));
        $agentId = $request->input('agent_id');
        $activeCustomer = $request->has('active_customer') ? (bool) $request->input('active_customer') : null;

        return ApiResource::make($this->personService->getAll($perPage, $customerType, $agentId, $activeCustomer));
    }

    /**
     * Detail
     * Displays the person with following relations
     * - Addresses
     * - Citizenship
     * - Role
     * - Meter list.
     *
     * @apiResourceModel App\Models\Person\Person
     *
     * @responseFile     responses/people/people.detail.json
     */
    public function show(int $personId): ApiResource
    {
        return ApiResource::make($this->personService->getDetails($personId, true));
    }

    /**
     * Create.
     */
    public function store(PersonRequest $request): JsonResponse
    {
        try {
            $customerType = $request->input('customer_type');
            $addressData = $this->addressService->createAddressDataFromRequest($request);
            $personData = $this->personService->createPersonDataFromRequest($request);
            $miniGridId = $request->input('mini_grid_id');
            DB::connection('tenant')->beginTransaction();
            if ($this->personService->isMaintenancePerson($customerType)) {
                $personData['mini_grid_id'] = $miniGridId;
                $person = $this->personService->createMaintenancePerson($personData);
            } else {
                $country = $this->countryService->getByCode($request->get('country_code'));
                $person = $this->personService->create($personData);

                if ($country instanceof Country) {
                    $person = $this->personService->addCitizenship($person, $country);
                }
            }

            $address = $this->addressService->make($addressData);
            $this->personAddressService->setAssignee($person);
            $this->personAddressService->setAssigned($address);
            $this->personAddressService->assign();
            $this->addressService->save($address);
            DB::connection('tenant')->commit();

            return ApiResource::make($person)->response()->setStatusCode(201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function importFromCsv(Request $request): ApiResource
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls',
            'mini_grid_id' => 'required|integer',
            'cluster_id' => 'required|integer',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        // ===============================
        // Parse File
        // ===============================
        if (in_array($ext, ['csv', 'txt'])) {
            $rows = array_map('str_getcsv', file($file->getRealPath()));
        } else {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
        }

        if (empty($rows)) {
            return ApiResource::make([
                'message' => 'No data found in file',
                'data' => []
            ]);
        }

        // Remove empty/meta rows
        $rows = array_filter(
            $rows,
            fn($r) =>
            count(array_filter($r, fn($v) => $v !== null && trim($v) !== '')) > 1
        );

        if (empty($rows)) {
            return ApiResource::make([
                'message' => 'No valid data rows found',
                'data' => []
            ]);
        }

        // ===============================
        // Header Processing
        // ===============================
        $header = array_map(fn($h) => trim((string)$h), array_shift($rows));

        if (empty(array_filter($header))) {
            $header = array_map(fn($i) => 'col_' . ($i + 1), array_keys($header));
        }

        $data = [];
        foreach ($rows as $row) {
            if (count($row) === count($header)) {
                $data[] = array_combine($header, $row);
            }
        }

        $miniGridId = (int) $request->mini_grid_id;
        $clusterId  = (int) $request->cluster_id;

        $parsed = [];

        foreach ($data as $row) {

            DB::beginTransaction();

            try {
                // ===============================
                // CITY
                // ===============================
                $cityName = trim($row['Address'] ?? '');
                $cityId = null;

                if (!$cityName) {
                    throw new \Exception('City/Address missing');
                }

                $city = $this->cityService->getByName($cityName);

                if (!$city) {
                    $cityData = [
                        'name'         => trim($cityName),
                        'mini_grid_id' => $miniGridId,
                        'cluster_id'   => $clusterId,
                        'country_id'   => 160,
                    ];

                    $cityRequest = \App\Http\Requests\CityRequest::create(
                        '/fake-url',
                        'POST',
                        $cityData
                    );

                    $cityController = app(\App\Http\Controllers\CityController::class);
                    $cityResponse = $cityController->store($cityRequest);

                    $responseData = $cityResponse->getData(true);

                    if (!$responseData || !isset($responseData['id'])) {
                        throw new \Exception('Failed to create City via controller');
                    }

                    $cityId = $responseData['id'];
                }

                // ===============================
                // PHONE / NI NUMBER SPLIT
                // ===============================
                $phoneRaw = trim($row['Phone'] ?? '');

                $parts = explode('#', $phoneRaw, 2);

                $phone    = isset($parts[0]) ? trim($parts[0]) : null;
                $niNumber = isset($parts[1]) ? trim($parts[1]) : null;

                $phone = preg_replace('/[^0-9]/', '', $phoneRaw);
                if (!$phone) {
                    $phone = rand(1000000000, 9999999999);
                }

                $fullName = trim((string)($row['Name'] ?? 'N/A'));

                $parts = explode(' ', $fullName);

                $firstName = $parts[0] ?? 'N/A';
                $surname   = $parts[1] ?? '';

                if (empty($surname)) {
                    $surname = $firstName;
                }

                // ===============================
                // CREATE CUSTOMER
                // ===============================
                $customerData = [
                    'national_id_number'      => $niNumber,
                    'external_customer_id'    => trim($row['Id']) ?? null,
                    'mini_grid_id'            => $miniGridId,
                    'name'                    => $firstName,
                    'serial_number'           => null,
                    'meter_type'              => 0,
                    'surname'                 => $surname,
                    'phone'                   => '+' . trim((string)$phone),
                    'tariff_id'               => 1,
                    'geo_points'              => '0,0',
                    'manufacturer'            => 1,
                    'connection_type_id'      => 1,
                    'connection_group_id'     => 1,
                    'city_id'                 => $cityId,
                ];

                $androidRequest = new \App\Http\Requests\AndroidAppRequest();
                $androidRequest->merge($customerData);

                $validator = validator($customerData, $androidRequest->rules());
                $validator->validate();

                $people = app(\App\Services\CustomerRegistrationAppService::class)
                    ->createCustomer($androidRequest);

                DB::commit();

                $parsed[] = [
                    'customer_id' => $people['id'] ?? null,
                    'city_id'     => $cityId
                ];
            } catch (\Throwable $e) {
                DB::rollBack();

                $parsed[] = [
                    'error' => $e->getMessage(),
                    'row'   => $row,
                ];
            }
        }

        return ApiResource::make([
            'message'    => 'Import processed successfully',
            'columns'    => $header,
            'total_rows' => count($parsed),
            'preview'    => array_slice($parsed, 0, 25),
        ]);
    }

    /**
     * Update
     * Updates the given parameter of that person.
     *
     * @urlParam  id required The ID of the person to update
     *
     * @bodyParam title string. The title of the person. Example: Dr.
     * @bodyParam name string. The title of the person. Example: Dr.
     * @bodyParam surname string. The title of the person. Example: Dr.
     * @bodyParam birth_date string. The title of the person. Example: Dr.
     * @bodyParam gender string. The title of the person. Example: Dr.
     * @bodyParam education string. The title of the person. Example: Dr.
     *
     * @apiResourceModel App\Models\Person\Person
     *
     * @responseFile     responses/people/person.update.json
     */
    public function update(
        int $personId,
        PersonRequest $request,
    ): ApiResource {
        $person = $this->personService->getById($personId);
        $personData = $request->all();

        return ApiResource::make($this->personService->update($person, $personData));
    }

    /**
     * Transactions
     * The list of all transactions(paginated) which belong to that person.
     * Each page contains 7 entries of the last transaction.
     *
     * @bodyParam    person_id int required the ID of the person. Example: 2
     *
     * @responseFile responses/people/person.transaction.list.json
     */
    public function transactions(
        int $personId,
    ): ApiResource {
        $person = $this->personService->getById($personId);

        return ApiResource::make($this->personService->getPersonTransactions($person));
    }

    /**
     * Search
     * Searches in person list according to the search term.
     *  Term could be one of the following attributes;
     * - phone number
     * - meter serial number
     * - name
     * - surname.
     *
     * @urlParam term  The ID of the post. Example: John Doe
     * @urlParam paginage int The page number. Example:1
     *
     * @responseFile responses/people/people.search.json
     */
    public function search(
        Request $request,
    ): ApiResource {
        $term = $request->input('term', '');
        $paginate = $request->input('paginate', 1);
        $per_page = $request->input('per_page', 15);

        return ApiResource::make($this->personService->searchPerson($term, $paginate, $per_page));
    }

    /**
     * Delete
     * Deletes that person with all his/her relations from the database. The person model uses soft deletes.
     * That means the orinal record wont be deleted but all mentioned relations will be removed permanently.
     *
     * @urlParam person required The ID of the person. Example:1
     *
     * @throws \Exception
     *
     * @apiResourceModel App\Models\Person\Person
     */
    public function destroy(
        int $personId,
    ): JsonResponse {
        $person = $this->personService->getById($personId);

        $deleted = $this->personService->delete($person);

        if (!$deleted) {
            return response()->json([
                'message' => 'Failed to delete person',
            ], 500);
        }

        return ApiResource::make([
            'message' => 'Person deleted successfully',
            'data' => $person,
        ])->response()->setStatusCode(200);
    }
}
