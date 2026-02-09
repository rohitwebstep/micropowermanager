<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Order\Order;
use App\Models\Person\Person;
use App\Services\Interfaces\IBaseService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @implements IBaseService<Order>
 */
class OrderService implements IBaseService
{
    public function __construct(
        private Order $order,
        private PersonService $personService
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
    public function getAll(
        ?int $limit = null,
        ?string $type = null,
        ?string $searchTerm = null
    ): LengthAwarePaginator {
        $query = $this->order->newQuery()
            ->with(['customer', 'meter', 'billingAddress', 'shippingAddress']);

        if ($type) {
            $query->where('type', $type);
        }

        // 🔍 Search only on orders table columns
        if (!empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('order_id', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('first_name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('phone_number', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('status', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('notes', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        return $query->paginate($limit);
    }

    /**
     * Create a new order
     */
    public function create(array $data): Order
    {
        // Handle customer creation if customer_id is null
        if (empty($data['customer_id'])) {
            $phone = $data['phone_number'] ?? null;

            if ($phone) {
                $person = $this->personService->getByPhoneNumber($phone);

                if (!$person instanceof Person) {
                    $person = $this->personService->createFromRequest(
                        new Request([
                            'name'        => $data['first_name'] ?? null,
                            'surname'     => $data['last_name'] ?? null,
                            'email'       => $data['email'] ?? null,
                            'phone'       => $phone,
                            'is_customer' => 1,
                        ])
                    );
                }

                $data['customer_id'] = $person->id;
            }
        }

        // Create order
        $order = $this->order->newQuery()->create($data);

        // Billing address
        if (!empty($data['billing_address'])) {
            $order->billingAddress()->updateOrCreate(
                ['type' => 'billing'],
                $data['billing_address']
            );
        }

        // Shipping address
        if (!empty($data['shipping_address'])) {
            $order->shippingAddress()->updateOrCreate(
                ['type' => 'shipping'],
                $data['shipping_address']
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
