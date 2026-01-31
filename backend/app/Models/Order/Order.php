<?php

namespace App\Models\Order;

use App\Models\Base\BaseModel;
use App\Models\Meter\Meter;
use App\Models\Person\Person;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends BaseModel
{
    /**
     * The database connection to be used by the model.
     */
    protected $connection = 'tenant';

    /**
     * The table associated with the model.
     */
    protected $table = 'orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'customer_id',
        'meter_id',
        'amount',
        'purchased_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount'         => 'decimal:2',
        'purchased_date' => 'date',
    ];

    /**
     * Order → Customer (people)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'customer_id');
    }

    /**
     * Order → Meter
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'meter_id');
    }
}
