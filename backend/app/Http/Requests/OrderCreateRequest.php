<?php

namespace App\Http\Requests;

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
            'amount' => ['required', 'numeric', 'min:0'],
            'purchased_at' => ['required', 'date'],

            // Optional customer info
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],

            // Product meta (array of products)
            'product_meta' => ['required', 'array'],
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
        $validator->sometimes('meter_id', 'required', fn($input) => in_array($input->type, ['meter_electricity_order']));

        // Require product_meta for product orders
        // $validator->sometimes('product_meta', 'required|array', fn($input) => $input->type === 'product_order');
    }

    public function prepareForValidation(): void
    {
        if (!$this->filled('order_id')) {
            $this->merge([
                'order_id' => $this->generateOrderId(),
            ]);
        }

        /*
        // Default product_meta and meter_id to null if not relevant
        if ($this->input('type') !== 'product_order') {
            $this->merge(['product_meta' => null]);
        }
        */
        if (in_array($this->input('type'), ['meter_order'])) {
            $this->merge(['meter_id' => null]);
        }
    }
}
