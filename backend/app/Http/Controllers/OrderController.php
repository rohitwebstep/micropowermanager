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
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * List Orders
     */
    public function index(Request $request): ApiResource
    {
        $limit = $request->input('limit', config('settings.paginate'));

        return ApiResource::make(
            $this->orderService->getAll($limit)
        );
    }

    /**
     * Create Order
     */
    public function store(OrderCreateRequest $request): ApiResource
    {
        return ApiResource::make(
            $this->orderService->create($request->validated())
        );
    }

    /**
     * Order Detail
     */
    public function show(int $orderId): ApiResource
    {
        return ApiResource::make(
            $this->orderService->getById($orderId)
        );
    }

    /**
     * Update Order
     */
    public function update(
        OrderUpdateRequest $request,
        Order $order
    ): ApiResource {
        $before = json_encode($order->toArray());

        $updated = $this->orderService->update(
            $order,
            $request->validated()
        );

        event(new NewLogEvent([
            'user_id'  => auth('api')->id(),
            'affected' => $order,
            'action'   => "Order updated from $before to " . json_encode($updated),
        ]));

        return ApiResource::make($updated);
    }

    /**
     * Delete Order
     */
    public function destroy(Order $order): JsonResponse
    {
        $this->orderService->delete($order);

        return response()->json(null, 204);
    }
}
