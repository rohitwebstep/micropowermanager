<?php

namespace App\Http\Requests;

use App\Models\Cluster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMiniGridRequest extends FormRequest
{
    public const PARAM_CLUSTER_ID = 'cluster_id';
    public const PARAM_CLUSTER_NAME = 'cluster_name';
    public const PARAM_NAME = 'name';
    public const PARAM_GEO_DATA = 'geo_data';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data before validation.
     * If cluster_id is missing but cluster_name is provided,
     * resolve and inject cluster_id.
     */
    protected function prepareForValidation(): void
    {
        if (
            !$this->filled(self::PARAM_CLUSTER_ID) &&
            $this->filled(self::PARAM_CLUSTER_NAME)
        ) {
            $cluster = Cluster::where('name', $this->input(self::PARAM_CLUSTER_NAME))->first();

            if ($cluster) {
                $this->merge([
                    self::PARAM_CLUSTER_ID => $cluster->id,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'min:3'],

            'cluster_id' => ['sometimes', 'integer'],
            'cluster_name' => ['sometimes', 'string'],

            'geo_data' => ['sometimes', 'array'],
        ];
    }

    /**
     * Custom validation checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {

            // If either cluster_id or cluster_name is present,
            // validate the cluster properly.

            if ($this->filled(self::PARAM_CLUSTER_ID)) {

                $cluster = Cluster::find($this->input(self::PARAM_CLUSTER_ID));

                if (!$cluster) {
                    $validator->errors()->add(
                        self::PARAM_CLUSTER_ID,
                        'Selected cluster does not exist.'
                    );
                }
            }

            // If cluster_name was provided but no matching cluster found
            if (
                $this->filled(self::PARAM_CLUSTER_NAME) &&
                !$this->filled(self::PARAM_CLUSTER_ID)
            ) {
                $validator->errors()->add(
                    self::PARAM_CLUSTER_NAME,
                    'No cluster found with the given name.'
                );
            }
        });
    }
}
