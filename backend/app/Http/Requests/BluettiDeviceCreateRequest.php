<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BluettiDeviceCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_name'   => ['required', 'string', 'max:255'],
            'serial_number' => ['required', 'string', 'max:255', 'unique:mysql.bluetti_devices,serial_number'],
            'client'        => ['required', 'string', 'max:255'],
            'style'         => ['required', 'string', 'max:255'],
            'created_date'  => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_name.required'   => 'Device Name is required.',
            'serial_number.required' => 'Serial Number is required.',
            'serial_number.unique'   => 'This Serial Number already exists.',
            'client.required'        => 'Client is required.',
            'style.required'         => 'Style is required.',
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->filled('created_date')) {
            $this->merge([
                'created_date' => now()->toDateTimeString(),
            ]);
        }
    }
}