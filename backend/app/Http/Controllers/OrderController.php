<?php

namespace App\Http\Controllers;

use App\Events\NewLogEvent;
use App\Http\Requests\OrderCreateRequest;
use App\Http\Requests\OrderUpdateRequest;
use App\Http\Resources\ApiResource;
use App\Models\Order\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Requests\MeterTypeCreateRequest;
use App\Services\MeterService;
use App\Services\PersonService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class OrderController extends Controller
{
    public function __construct(
        private MeterService $meterService,
        private OrderService $orderService,
        private PersonService $personService,
    ) {}

    // List orders
    public function index(Request $request): ApiResource
    {
        $limit      = $request->input('limit', config('settings.paginate'));
        $type       = $request->input('type');    // optional
        $searchTerm = $request->input('search');  // optional

        return ApiResource::make(
            $this->orderService->getAll($limit, $type, $searchTerm)
        );
    }

    // Analytics
    public function analytics(Request $request): ApiResource
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $data = $this->orderService->analytics($from, $to);

        return ApiResource::make($data);
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
        $rows = array_filter(
            $rows,
            fn($r) =>
            count(array_filter($r, fn($v) => $v !== null && $v !== '')) > 1
        );

        if (empty($rows)) {
            return ApiResource::make([
                'message' => 'No valid data rows',
                'data' => []
            ]);
        }

        // HEADER
        $header = array_map('trim', array_shift($rows));

        // Auto-generate header if empty
        if (empty(array_filter($header))) {
            $header = array_map(fn($i) => 'col_' . ($i + 1), array_keys($header));
        }

        // Map rows to associative arrays safely
        $data = [];
        foreach ($rows as $r) {
            if (count($r) === count($header)) {
                $data[] = array_combine($header, $r);
            }
        }

        $parsed = [];

        foreach ($data as $row) {

            $externalCustomerId = $row['Customer No.'] ?? null;

            if (!$externalCustomerId) {
                $parsed[] = [
                    'error' => 'Missing Customer No.',
                    'row_data' => $row,
                ];
                continue;
            }

            $people = $this->personService->getByExternalCustomerId($externalCustomerId);

            if (!$people) {
                $parsed[] = [
                    'error' => 'Person not found',
                    'row_data' => $row,
                ];
                continue;
            }

            $phase = 1;
            $maxCurrent = 100;
            $meterTypeId = null;
            $meterId = null;
            $peopleId = $people->id;

            // ===== Create / Get MeterType =====
            try {

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

                    $responseData = $meterTypeResponse->resolve() ?? null;

                    if (!$responseData || !isset($responseData['id'])) {
                        throw new \Exception('Failed to create MeterType via controller');
                    }

                    $meterTypeId = $responseData['id'];
                    $meterTypeModel = \App\Models\Meter\MeterType::find($meterTypeId);

                    if (!$meterTypeModel) {
                        throw new \Exception("MeterType with ID {$meterTypeId} not found after creation");
                    }

                    $meterType = $meterTypeModel->toArray();
                }
            } catch (\Exception $e) {
                $meterType = [
                    'error' => $e->getMessage(),
                    'data_attempted' => $meterTypeData ?? null,
                ];
            }

            // ===== Validate Customer / Meter =====
            try {

                $customerRequestData = [
                    'name'                => $people->name,
                    'serial_number'       => $row['Meter No.'],
                    'meter_type'          => $meterTypeId ?? 0,
                    'surname'             => $people->surname,
                    'phone'               => $people->addresses[0]->phone ?? null,
                    'tariff_id'           => 1,
                    'geo_points'          => '0,0',
                    'manufacturer'        => 1,
                    'connection_type_id'  => 1,
                    'connection_group_id' => 1,
                    'city_id'             => 1,
                ];

                $androidRequest = new \App\Http\Requests\AndroidAppRequest();
                $androidRequest->merge($customerRequestData);

                $validator = validator($customerRequestData, $androidRequest->rules());
                $validator->validate();

                $people = app(\App\Services\CustomerRegistrationAppService::class)
                    ->createCustomer($androidRequest);

                $meter = $this->meterService->getBySerialNumber($row['Meter No.'] ?? null);
                $meterId = $meter ? $meter->id : null;

                $person = [
                    'Serial Number' => $row['Meter No.'],
                    'meter' => $meter ?? null,
                ];
            } catch (\Throwable $e) {
                $person = [
                    'error' => $e->getMessage(),
                    'data_attempted' => $customerRequestData ?? null,
                ];
            }

            // ===== Create Order =====
            if ($meterId) {

                try {

                    $orderToken = $row['Token'] ?? null;

                    if ($orderToken) {
                        $existingOrder = Order::where('token', $orderToken)->first();
                        if ($existingOrder) {
                            throw new \Exception("Order with token {$orderToken} already exists");
                        }
                    }

                    $orderGeneratedId = 'MPM-ODR-' . now()->format('d-m-Y') . '-' . random_int(100000, 999999);

                    $orderRequestData = [
                        'order_id'      => $orderGeneratedId,
                        'customer_id'   => $peopleId,
                        'type'          => 'meter_electricity_order',
                        'meter_id'      => $meterId,
                        'serial_number' => trim($row['Meter No.'] ?? ''),
                        'amount'        => preg_replace('/[^0-9.]/', '', $row['Total Paid'] ?? 0),
                        'token'         => $orderToken,
                        'purchased_at'  => !empty($row['Created Date'])
                            ? date('Y-m-d H:i:s', strtotime($row['Created Date']))
                            : now(),
                        'first_name'    => $people->name,
                        'last_name'     => $people->surname ?? $people->name,
                        'phone_number'  => $people->addresses[0]->phone ?? null,
                    ];

                    $orderRequest = new \App\Http\Requests\OrderCreateRequest();
                    $validator = validator($orderRequestData, $orderRequest->rules());

                    if ($validator->fails()) {
                        throw new \Exception($validator->errors()->first());
                    }

                    $validatedData = $validator->validated();
                    $order = $this->orderService->create($validatedData);
                } catch (\Throwable $e) {
                    $person = [
                        'error' => $e->getMessage(),
                        'data_attempted' => $orderRequestData ?? null,
                    ];
                }
            }

            // ===== Vending / Transaction =====
            $vend = [
                'price'      => preg_replace('/[^0-9.]/', '', $row['Price'] ?? null),
                'tax'        => preg_replace('/[^0-9.]/', '', $row['Tax'] ?? null),
                'unit'       => preg_replace('/[^0-9.]/', '', $row['Total Unit'] ?? null),
                'total_paid' => preg_replace('/[^0-9.]/', '', $row['Total Paid'] ?? null),
                'token'      => $row['Token'] ?? null,
                'date'       => !empty($row['Create Date'])
                    ? date('Y-m-d H:i:s', strtotime($row['Create Date']))
                    : null,
                'operator'   => $row['Operator'] ?? null,
            ];

            $parsed[] = compact('meterType', 'person', 'vend');
        }

        return ApiResource::make([
            'message'    => 'Parsed successfully',
            'columns'    => $header,
            'total_rows' => count($parsed),
            'preview'    => array_slice($parsed, 0, 25),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $debug = $request->boolean('debug', false);
        $template = $request->input('template', 1);

        // You can also hardcode: $debug = true;

        $from = $request->input('from');
        $to   = $request->input('to');

        $query = \App\Models\Order\Order::with(['meter.meter_type', 'shippingAddress', 'billingAddress'])
            ->where('type', 'meter_order')
            ->whereNull('meter_id');

        if ($from && $to) {
            $query->whereBetween('purchased_at', [
                \Carbon\Carbon::parse($from)->startOfDay(),
                \Carbon\Carbon::parse($to)->endOfDay()
            ]);
        }

        $orders = $query->get();

        /*
    |--------------------------------------------------------------------------
    | DEBUG MODE → Return Array Instead of Excel
    |--------------------------------------------------------------------------
    */
        if ($debug) {

            if ($template == 2) {
                return response()->json(
                    $orders->map(function ($order) {

                        $address = $order->shippingAddress;

                        return [
                            'id'   => $order->id,
                            'name' => trim($order->first_name . ' ' . $order->last_name),
                            'address' => $address
                                ? implode(', ', array_filter([
                                    $address->address1,
                                    $address->address2,
                                    $address->city,
                                    $address->state,
                                ]))
                                : '',
                            'phone' => $order->phone_number ?? '',
                        ];
                    })
                );
            }

            // Default Template 1 (existing)
            return response()->json(
                $orders->map(function ($order) {
                    return [
                        'customer_no'   => '',
                        'customer_name' => $order->first_name . ' ' . $order->last_name,
                        'meter_no'      => '',
                        'price'         => $order->amount,
                        'tax'           => '',
                        'total_unit'    => '',
                        'total_paid'    => '',
                        'operator'      => 'pos1',
                        'token'         => '',
                        'created_date'  => $order->purchased_at,
                    ];
                })
            );
        }

        /*
    |--------------------------------------------------------------------------
    | NORMAL MODE → Generate Excel
    |--------------------------------------------------------------------------
    */

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        /*
    |--------------------------------------------------------------------------
    | TEMPLATE 2 → Simple Export
    |--------------------------------------------------------------------------
    */
        if ($template == 2) {

            $headers = ['Id', 'Name', 'Address', 'Phone'];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:D1')->getFont()->setBold(true);

            $row = 2;

            foreach ($orders as $order) {

                $addressModel = $order->shippingAddress;

                $fullAddress = $addressModel
                    ? implode(', ', array_filter([
                        $addressModel->address1,
                        $addressModel->address2,
                        $addressModel->city,
                        $addressModel->state,
                    ]))
                    : '';

                $sheet->setCellValue("A{$row}", $order->id);
                $sheet->setCellValue("B{$row}", trim($order->first_name . ' ' . $order->last_name));
                $sheet->setCellValue("C{$row}", $fullAddress);

                // Force phone as string (prevents scientific notation)
                $sheet->setCellValueExplicit(
                    "D{$row}",
                    $order->phone_number ?? '',
                    DataType::TYPE_STRING
                );

                $row++;
            }

            $fileName = 'SimpleRecords.xlsx';
        }

        /*
    |--------------------------------------------------------------------------
    | TEMPLATE 1 → Existing Vending Format
    |--------------------------------------------------------------------------
    */ else {

            $headers = [
                'Customer No.',
                'Customer Name',
                'Meter No.',
                'Price',
                'Tax',
                'Total Unit',
                'Total Paid',
                'Operator',
                'Token',
                'Created Date'
            ];

            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:J1')->getFont()->setBold(true);

            $row = 2;

            foreach ($orders as $order) {
                $sheet->setCellValue("A{$row}", "");
                $sheet->setCellValue("B{$row}", $order->first_name . ' ' . $order->last_name);
                $sheet->setCellValue("C{$row}", $order->meter->serial_number ?? '');
                $sheet->setCellValue("D{$row}", $order->amount);
                $sheet->setCellValue("E{$row}", "");
                $sheet->setCellValue("F{$row}", $order->meter->meter_type->max_current ?? '');
                $sheet->setCellValue("G{$row}", "");
                $sheet->setCellValue("H{$row}", "pos1");
                $sheet->setCellValue("I{$row}", $order->token);
                $sheet->setCellValue("J{$row}", $order->purchased_at);
                $row++;
            }

            $fileName = 'VendingRecords.xlsx';
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'VendingRecords.xlsx';
        $temp_file = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($temp_file);

        return response()->download($temp_file, $fileName)->deleteFileAfterSend(true);
    }

    // Create order
    public function store(OrderCreateRequest $request): ApiResource
    {
        $data = $request->validated();

        // return ApiResource::make($data);

        $order = $this->orderService->create($data);

        return ApiResource::make($order);
    }

    public function assignExternalDetails(Request $request, $order_id)
    {
        $request->validate([
            'external_customer_id' => ['required', 'string'],
            'serial_number' => ['required', 'string'],
            'max_current'   => ['required', 'numeric'],
            'phase'         => ['required', 'numeric', 'in:1,3'],
            'phone'         => ['required', 'string'],
        ]);

        try {

            // =============================
            // Find Order
            // =============================
            $order = \App\Models\Order\Order::findOrFail($order_id);

            $meterTypeId = null;

            // =============================
            // Create / Get MeterType
            // =============================
            try {

                $maxCurrent = $request->max_current;
                $phase = $request->phase;

                $existingMeterType = \App\Models\Meter\MeterType::where('max_current', $maxCurrent)
                    ->where('phase', $phase)
                    ->where('online', 1)
                    ->first();

                if ($existingMeterType) {

                    $meterTypeId = $existingMeterType->id;
                } else {

                    $meterTypeData = [
                        'max_current' => $maxCurrent,
                        'phase'       => $phase,
                        'online'      => 1,
                    ];

                    $meterTypeRequest = \App\Http\Requests\MeterTypeCreateRequest::create(
                        '/fake-url',
                        'POST',
                        $meterTypeData
                    );

                    $meterTypeController = app(\App\Http\Controllers\MeterTypeController::class);
                    $meterTypeResponse = $meterTypeController->store($meterTypeRequest);

                    $responseData = $meterTypeResponse->getData(true);

                    if (!$responseData || !isset($responseData['id'])) {
                        throw new \Exception('Failed to create MeterType via controller');
                    }

                    $meterTypeId = $responseData['id'];
                }
            } catch (\Exception $e) {
                throw new \Exception('MeterType Error: ' . $e->getMessage());
            }

            // =============================
            // Create Customer
            // =============================
            try {

                $customerRequestData = [
                    'name'                => $order->first_name,
                    'surname'             => $order->last_name,
                    'serial_number'       => $request->serial_number,
                    'meter_type'          => $meterTypeId ?? 0,
                    'phone'               => '+' . $request->phone,
                    'tariff_id'           => 1,
                    'geo_points'          => '0,0',
                    'manufacturer'        => 1,
                    'connection_type_id'  => 1,
                    'connection_group_id' => 1,
                    'city_id'             => 1,
                ];

                $androidRequest = new \App\Http\Requests\AndroidAppRequest();
                $androidRequest->merge($customerRequestData);

                $validator = validator($customerRequestData, $androidRequest->rules());
                $androidRequest->setValidator($validator);
                $androidRequest->validated();

                $people = app(\App\Services\CustomerRegistrationAppService::class)
                    ->createCustomer($androidRequest);

                $peopleId = $people['id'] ?? null;
            } catch (\Throwable $e) {
                throw new \Exception('Customer Error: ' . $e->getMessage());
            }

            // =============================
            // Find Meter
            // =============================
            $meter = \App\Models\Meter\Meter::where('serial_number', $request->serial_number)
                ->firstOrFail();

            // =============================
            // Assign meter to order
            // =============================
            $order->update([
                'meter_id'     => $meter->id,
                'external_customer_id' => $request->external_customer_id,
            ]);

            return response()->json([
                'message' => 'Meter assigned successfully.',
                'order'   => $order->fresh(),
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Failed to assign meter.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // Show order
    public function show(int $orderId): ApiResource
    {
        $order = $this->orderService->getById($orderId);
        return ApiResource::make($order);
    }

    // Update order
    public function update(OrderUpdateRequest $request, Order $order): ApiResource
    {
        $before = json_encode($order->toArray());
        $data = $request->validated();
        if (($data['type'] ?? null) !== 'product_order') {
            $data['product_meta'] = null;
        }
        $updated = $this->orderService->update($order, $data);

        event(new NewLogEvent([
            'user_id'  => auth('api')->id(),
            'affected' => $order,
            'action'   => "Order updated from $before to " . json_encode($updated),
        ]));

        return ApiResource::make($updated);
    }

    // Delete order
    public function destroy(Order $order): JsonResponse
    {
        $this->orderService->delete($order);
        return response()->json(null, 204);
    }
}
