<?php

namespace App\Http\Requests;

use App\Models\Meter\Meter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'string', 'unique:tenant.orders,order_id'],
            'customer_id' => ['nullable', 'numeric', 'exists:tenant.people,id'],
            'type' => ['required', Rule::in(['meter_order', 'meter_electricity_order', 'product_order'])],
            'meter_id' => ['nullable', 'numeric', 'exists:tenant.meters,id'],
            'serial_number' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'power_code' => ['nullable', 'string', 'max:255'],
            'token'      => ['nullable', 'string', 'max:255'],
            'purchased_at' => ['nullable', 'date'],

            // Optional customer info
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'state_name' => ['required', 'string', 'exists:tenant.clusters,name'],
            'mini_grid_id' => ['required', 'numeric', 'exists:tenant.mini_grids,id'],
            'city_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id_number' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string', 'max:20'],

            // Product meta (array of products)
            'product_meta' => ['nullable', 'array'],
            'product_meta.*.product_name' => ['required', 'string', 'max:255'],
            'product_meta.*.quantity' => ['required', 'numeric', 'min:1'],

            // Billing address (optional)
            'billing_address' => ['nullable', 'array'],
            'billing_address.first_name' => ['nullable', 'string', 'max:255'],
            'billing_address.last_name' => ['nullable', 'string', 'max:255'],
            'billing_address.address1' => ['nullable', 'string', 'max:255'],
            'billing_address.address2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:255'],
            'billing_address.state' => ['nullable', 'string', 'max:255'],
            'billing_address.phone_number' => ['nullable', 'string', 'max:20'],

            // Shipping address (optional)
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.first_name' => ['nullable', 'string', 'max:255'],
            'shipping_address.last_name' => ['nullable', 'string', 'max:255'],
            'shipping_address.address1' => ['nullable', 'string', 'max:255'],
            'shipping_address.address2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['nullable', 'string', 'max:255'],
            'shipping_address.state' => ['nullable', 'string', 'max:255'],
            'shipping_address.phone_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function generateOrderId(): string
    {
        $date = now()->format('d-m-Y');
        $random = random_int(100000, 999999);

        return "MPM-ODR-{$date}-{$random}";
    }

    public function withValidator($validator)
    {
        // Require meter_id for electricity orders
        // $validator->sometimes('meter_id', 'required', fn($input) => in_array($input->type, ['meter_electricity_order']));

        // Require order_id when type is NOT product_order
        $validator->sometimes(
            'order_id',
            'required',
            fn($input) => $input->type !== 'product_order'
        );

        // Require power_code & token for electricity orders
        $validator->sometimes(
            ['power_code', 'token', 'purchased_at'],
            'required',
            fn($input) => $input->type === 'meter_electricity_order'
        );

        // Require either meter_id OR serial_number
        $validator->sometimes(
            ['meter_id', 'serial_number'],
            'required_without_all:meter_id,serial_number',
            fn($input) => $input->type === 'meter_electricity_order'
        );

        // Validate serial_number existence (if provided)
        $validator->after(function ($validator) {

            if (
                $this->input('type') === 'meter_electricity_order'
                && !$this->filled('meter_id')
                && $this->filled('serial_number')
            ) {

                $this->resolvedMeter = Meter::where('serial_number', $this->serial_number)->first();

                if (!$this->resolvedMeter) {
                    $validator->errors()->add(
                        'serial_number',
                        'Invalid serial number - ' . $this->serial_number
                    );
                }
            }
        });

        // Require product_meta for product orders
        $validator->sometimes('product_meta', 'required|array', fn($input) => $input->type === 'product_order');
    }

    public function prepareForValidation(): void
    {
        if (!$this->filled('order_id')) {
            $this->merge([
                'order_id' => $this->generateOrderId(),
            ]);
        }

        // Default product_meta and meter_id to null if not relevant
        if ($this->input('type') !== 'product_order') {
            $this->merge(['product_meta' => null]);
        }

        if (in_array($this->input('type'), ['meter_order'])) {
            $this->merge(['meter_id' => null]);
        }

        // Auto-resolve meter from serial_number
        if (
            $this->input('type') === 'meter_electricity_order'
            && !$this->filled('meter_id')
            && $this->filled('serial_number')
        ) {

            $meter = Meter::where('serial_number', $this->serial_number)->first();

            if ($meter) {
                $this->merge([
                    'meter_id' => $meter->id,
                ]);
            }
        }

        // Nullify power_code & token if not electricity order
        if ($this->input('type') !== 'meter_electricity_order') {
            $this->merge([
                'power_code' => null,
                'token' => null,
            ]);
        }
    }
}
