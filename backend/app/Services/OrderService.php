<?php

namespace App\Services;

use Carbon\Carbon;
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
        private PersonService $personService,
        private CityService $cityService,
        private ClusterService $clusterService
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
                $columns = [
                    'order_id',
                    'first_name',
                    'last_name',
                    'email',
                    'phone_number',
                    'status',
                    'notes',
                    'type',
                    'amount',
                    'power_code',
                    'token',
                ];

                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$searchTerm}%");
                }
            });
        }

        return $query->paginate($limit);
    }

    public function analytics(?string $from = null, ?string $to = null): array
    {
        $query = $this->order->newQuery();

        // Apply purchase date filter
        if ($from && $to) {
            $query->whereBetween('purchased_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay()
            ]);
        } elseif ($from) {
            $query->whereDate('purchased_at', '>=', Carbon::parse($from));
        } elseif ($to) {
            $query->whereDate('purchased_at', '<=', Carbon::parse($to));
        }

        $results = (clone $query)
            ->groupBy('type')
            ->select('type')
            ->selectRaw('COUNT(id) as total_orders')
            ->selectRaw('SUM(amount) as total_amount')
            ->get();

        return [
            'filters' => [
                'from' => $from,
                'to'   => $to,
            ],
            'summary' => $results,
            'grand_total_orders' => $query->count(),
            'grand_total_amount' => $query->sum('amount'),
        ];
    }

    /**
     * Create a new order
     */
    public function create(array $data): Order
    {
        // ===============================
        // CITY
        // ===============================
        $stateName = trim($data['state_name'] ?? '');

        if (!$stateName) {
            throw new \Exception('state_name missing');
        }

        $cluster = $this->clusterService->getByName($stateName);

        // ===============================
        // CITY
        // ===============================
        $cityName = trim($data['city_name'] ?? '');
        $cityId = null;

        if (!$cityName) {
            throw new \Exception('city_name missing');
        }

        $city = $this->cityService->getByName($cityName);

        if (!$city) {
            $cityData = [
                'name'         => trim($cityName),
                'mini_grid_id' => $data['mini_grid_id'],
                'cluster_id'   => $cluster->id,
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

        // Handle customer creation if customer_id is null
        if (empty($data['customer_id'])) {
            $phone = $data['phone_number'] ?? null;

            if ($phone) {
                $person = $this->personService->getByPhoneNumber($phone);

                if (!$person instanceof Person) {
                    $customerRequestData = [
                        'name'                => $data['first_name'] ?? null,
                        'serial_number'       => null,
                        'meter_type'          => $meterTypeId ?? 0,
                        'surname'             => $data['last_name'] ?? null,
                        'phone'               => $phone,
                        'tariff_id'           => 1,
                        'geo_points'          => '0, 0',
                        'manufacturer'        => 1,
                        'connection_type_id'  => 1,
                        'connection_group_id' => 1,
                        'city_id'             => $cityId,
                    ];

                    $androidRequest = new \App\Http\Requests\AndroidAppRequest();
                    $androidRequest->merge($customerRequestData);

                    $validator = validator($customerRequestData, $androidRequest->rules());
                    $validator->validate();

                    $person = app(\App\Services\CustomerRegistrationAppService::class)
                        ->createCustomer($androidRequest);
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
