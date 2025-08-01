<?php

namespace App\Services;

use App\Models\Country;
use App\Services\Interfaces\IBaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @implements IBaseService<Country>
 */
class CountryService implements IBaseService
{
    public function __construct(
        private Country $country,
    ) {}

    /*
    public function getByCode(?string $countryCode): Country {
        return $countryCode !== null ? $this->country->where('country_code', $countryCode)->first() : $countryCode;
    }
    */

    public function getByCode(?string $countryCode): Country
    {
        return $countryCode !== null
            ? $this->country->where('country_code', $countryCode)->first()
            : $this->country->first();
    }

    public function getById(int $id): Country
    {
        return $this->country->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Country
    {
        return $this->country->create([
            'country_code' => $data['country_code'],
            'country_name' => $data['country_name'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update($model, array $data): Country
    {
        $model->update([
            'country_code' => $data['country_code'] ?? $model->country_code,
            'country_name' => $data['country_name'] ?? $model->country_name,
        ]);

        return $model;
    }

    public function delete($model): ?bool
    {
        return $model->delete();
    }

    /**
     * @return Collection<int, Country>
     */
    public function getAll(?int $limit = null): Collection
    {
        $query = $this->country->query();

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
