<?php

namespace App\Http\Requests;

use App\Models\MiniGrid;
use App\Models\Cluster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMiniGridRequest extends FormRequest
{
    public const PARAM_CLUSTER_ID = 'cluster_id';
    public const PARAM_CLUSTER_NAME = 'cluster_name';
    public const PARAM_NAME = 'name';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data before validation.
     * If cluster_id is missing but cluster_name is provided,
     * try to resolve and inject cluster_id.
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

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'min:3'],

            // Removed exists rule to prevent SQL error
            'cluster_id' => ['nullable', 'integer'],
            'cluster_name' => ['nullable', 'string'],

            'geo_data' => ['required'],
        ];
    }

    /**
     * Custom validation checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {

            // Ensure at least one of cluster_id or cluster_name is provided
            if (
                !$this->filled(self::PARAM_CLUSTER_ID) &&
                !$this->filled(self::PARAM_CLUSTER_NAME)
            ) {
                $validator->errors()->add(
                    'cluster',
                    'Either cluster_id or cluster_name must be provided.'
                );
                return;
            }

            // If cluster_id is present, verify it exists
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

    /**
     * Build MiniGrid model instance.
     */
    public function getMiniGrid(): MiniGrid
    {
        $miniGrid = new MiniGrid();
        $miniGrid->setClusterId($this->input(self::PARAM_CLUSTER_ID));
        $miniGrid->setName($this->input(self::PARAM_NAME));

        return $miniGrid;
    }
}
