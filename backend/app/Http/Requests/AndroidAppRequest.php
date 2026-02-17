<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AndroidAppRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array {
        return [
            'external_customer_id' => ['nullable', 'string'],
            'mini_grid_id' => ['nullable', 'integer'],
            'name' => ['required', 'min:3'],
            'surname' => ['required', 'min:3'],
            'phone' => ['required', 'min:11', 'regex:(^\+)', 'numeric'],
            'tariff_id' => ['required'],
            'geo_points' => ['required'],
            // 'serial_number' => ['required', 'string'],
            'serial_number' => ['nullable', 'string'],
            'manufacturer' => ['required'],
            'meter_type' => ['required'],
        ];
    }
}
