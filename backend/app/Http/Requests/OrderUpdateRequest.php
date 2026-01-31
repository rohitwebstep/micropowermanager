<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'numeric',
            ],
            'order_id' => [
                'sometimes',
                'string',
                "unique:tenant.orders,order_id,{$this->id}",
            ],
            'customer_id' => [
                'sometimes',
                'numeric',
                'exists:tenant.people,id',
            ],
            'meter_id' => [
                'sometimes',
                'numeric',
                'exists:tenant.meters,id',
            ],
            'amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'purchased_date' => [
                'sometimes',
                'date',
            ],
        ];
    }
}
