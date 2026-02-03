<?php

namespace App\Models\Order;

use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends BaseModel
{
    /**
     * The database connection to be used by the model.
     */
    protected $connection = 'tenant';

    /**
     * The table associated with the model.
     */
    protected $table = 'order_addresses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'type',          // billing or shipping
        'first_name',
        'last_name',
        'address1',
        'address2',
        'city',
        'state',
        'phone_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => 'string',
    ];

    /**
     * OrderAddress → Order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
