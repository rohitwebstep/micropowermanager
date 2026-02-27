<?php

namespace App\Models\ExternalPortalTransaction;

use App\Models\Base\BaseModel;
use App\Models\Person\Person;
use App\Models\Transaction\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Class ExternalPortalTransaction
 *
 * @property int $id
 * @property string $reference_id
 * @property int|null $customer_id
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property float $amount
 * @property string|null $payment_method
 * @property string $status
 */
class ExternalPortalTransaction extends BaseModel
{
    use HasFactory;

    protected $table = 'external_portal_transactions';

    protected $guarded = [];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Customer (Person)
     *
     * @return BelongsTo<Person, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'customer_id', 'id');
    }

    /**
     * Polymorphic Transaction relation
     *
     * @return MorphOne<Transaction, $this>
     */
    public function transaction(): MorphOne
    {
        return $this->morphOne(
            Transaction::class,
            'originalTransaction'
        );
    }
}
