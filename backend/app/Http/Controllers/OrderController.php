<?php

namespace App\Http\Controllers;

use App\Events\NewLogEvent;
use App\Http\Requests\OrderCreateRequest;
use App\Http\Requests\OrderUpdateRequest;
use App\Http\Resources\ApiResource;
use App\Models\Meter\Meter;
use App\Models\Order\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

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

    public function exportExcel(Request $request)
    {
        $debug = $request->boolean('debug', false);
        // You can also hardcode: $debug = true;

        $from = $request->input('from');
        $to   = $request->input('to');

        $query = \App\Models\Order\Order::with(['meter.meter_type'])
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

            $data = $orders->map(function ($order) {
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
            });

            return response()->json($data);
        }

        /*
    |--------------------------------------------------------------------------
    | NORMAL MODE → Generate Excel
    |--------------------------------------------------------------------------
    */

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

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
