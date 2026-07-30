<?php

namespace App\Models\Person;

use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $person_id
 * @property string $external_id
 * @property string $portal_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Person $person
 * 
 * @method static Builder|PersonExternalId forPortal(string $portal)
 * @method static Builder|PersonExternalId forExternalId(string $id)
 */
class PersonExternalId extends BaseModel
{

    protected $table = 'people_external_ids';

    protected $fillable = [
        'person_id',
        'external_id',
        'portal_name'
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'id');
    }

    /**
     * Professional Upsert: Portal aur ID ke base par record update ya create karega.
     */
    public static function upsertExternalId(int $personId, string $portalName, string $externalId): self
    {
        return self::updateOrCreate(
            [
                'portal_name' => $portalName,
                'external_id' => $externalId,
            ],
            [
                'person_id' => $personId,
            ]
        );
    }

    /**
     * Scope: Filter by portal name
     */
    public function scopeForPortal(Builder $query, string $portal): Builder
    {
        return $query->where('portal_name', $portal);
    }

    /**
     * Scope: Filter by external ID
     */
    public function scopeForExternalId(Builder $query, string $id): Builder
    {
        return $query->where('external_id', $id);
    }
}
