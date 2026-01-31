<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Services\Interfaces\IBaseService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @implements IBaseService<Order>
 */
class OrderService implements IBaseService
{
    public function __construct(
        private Order $order
    ) {}

    public function getById(int $orderId): ?Order
    {
        return $this->order->newQuery()
            ->with(['customer', 'meter'])
            ->find($orderId);
    }

    public function getAll(?int $limit = null): LengthAwarePaginator
    {
        return $this->order->newQuery()
            ->with(['customer', 'meter'])
            ->paginate($limit);
    }

    public function create(array $data): Order
    {
        return $this->order->newQuery()->create($data);
    }

    /**
     * @param Order $order
     */
    public function update($order, array $data): Order
    {
        $order->update($data);
        return $order->fresh();
    }

    /**
     * @param Order $order
     */
    public function delete($order): ?bool
    {
        return $order->delete();
    }
}
