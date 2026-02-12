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

    // Create order
    public function store(OrderCreateRequest $request): ApiResource
    {
        $data = $request->validated();

        // return ApiResource::make($data);

        $order = $this->orderService->create($data);

        return ApiResource::make($order);
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
