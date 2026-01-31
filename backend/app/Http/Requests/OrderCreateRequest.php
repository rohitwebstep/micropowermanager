<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => [
                'required',
                'string',
                'unique:tenant.orders,order_id', // ✅ tenant DB
            ],
            'customer_id' => [
                'required',
                'numeric',
                'exists:tenant.people,id', // ✅ tenant DB
            ],
            'meter_id' => [
                'required',
                'numeric',
                'exists:tenant.meters,id', // ✅ tenant DB
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'purchased_date' => [
                'required',
                'date',
            ],
        ];
    }
}
