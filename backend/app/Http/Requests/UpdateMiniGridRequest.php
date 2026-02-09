<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMiniGridRequest extends FormRequest {
    public const PARAM_CLUSTER_ID = 'cluster_id';
    public const PARAM_NAME = 'name';
    public const PARAM_GEO_DATA = 'geo_data';

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'name' => ['sometimes', 'min:3'],
            'cluster_id' => ['sometimes'],
            'geo_data' => ['sometimes', 'array'],
        ];
    }
}
