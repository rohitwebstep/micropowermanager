<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BluettiDeviceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Works for both route model binding and plain {id}
        $deviceId = $this->route('bluetti_device') ?? $this->route('id');

        return [
            'device_name'   => ['sometimes', 'string', 'max:255'],
            'serial_number' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('mysql.bluetti_devices', 'serial_number')->ignore($deviceId),
            ],
            'client'       => ['sometimes', 'string', 'max:255'],
            'style'        => ['sometimes', 'string', 'max:255'],
            'created_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'serial_number.unique' => 'This Serial Number already exists.',
        ];
    }
}