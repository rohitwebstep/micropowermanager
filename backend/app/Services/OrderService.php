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

    /**
     * Get order by ID with relationships
     */
    public function getById(int $orderId): ?Order
    {
        return $this->order->newQuery()
            ->with(['customer', 'meter', 'billingAddress', 'shippingAddress'])
            ->find($orderId);
    }

    /**
     * Get all orders (paginated) with relationships
     */
    public function getAll(?int $limit = null, ?string $type = null): LengthAwarePaginator
    {
        $query = $this->order->newQuery()
            ->with(['customer', 'meter', 'billingAddress', 'shippingAddress']);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->paginate($limit);
    }

    /**
     * Create a new order
     */
    public function create(array $data): Order
    {
        // Create order
        $order = $this->order->newQuery()->create($data);

        // Billing address
        if (!empty($data['billing_address'])) {
            $order->billingAddress()->updateOrCreate(
                ['type' => 'billing'],       // Match on type (unique key)
                $data['billing_address']     // Fill with new data
            );
        }

        // Shipping address
        if (!empty($data['shipping_address'])) {
            $order->shippingAddress()->updateOrCreate(
                ['type' => 'shipping'],      // Match on type (unique key)
                $data['shipping_address']    // Fill with new data
            );
        }

        return $order->fresh(['customer', 'meter', 'billingAddress', 'shippingAddress']);
    }

    /**
     * Update an existing order
     *
     * @param Order $order
     */
    public function update($order, array $data): Order
    {
        $order->update($data);

        // Update addresses if provided
        if (!empty($data['billing_address'])) {
            $order->billingAddress()->updateOrCreate(
                ['order_id' => $order->id, 'type' => 'billing'],
                $data['billing_address']
            );
        }

        if (!empty($data['shipping_address'])) {
            $order->shippingAddress()->updateOrCreate(
                ['order_id' => $order->id, 'type' => 'shipping'],
                $data['shipping_address']
            );
        }

        return $order->fresh(['customer', 'meter', 'billingAddress', 'shippingAddress']);
    }

    /**
     * Delete an order
     *
     * @param Order $order
     */
    public function delete($order): ?bool
    {
        return $order->delete();
    }
}
