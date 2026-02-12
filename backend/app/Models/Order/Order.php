<?php

namespace App\Models\Order;

use App\Models\Base\BaseModel;
use App\Models\Meter\Meter;
use App\Models\Person\Person;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends BaseModel
{
    protected $connection = 'tenant';
    protected $table = 'orders';

    protected $fillable = [
        'order_id',
        'customer_id',
        'meter_id',
        'type',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'status',
        'amount',
        'power_code',
        'token',
        'purchased_at',
        'notes',
        'product_meta',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'purchased_at' => 'datetime',
        'product_meta' => 'array',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'customer_id');
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'meter_id');
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class, 'order_id')->where('type', 'billing');
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class, 'order_id')->where('type', 'shipping');
    }

    // Order type checks
    public function isMeterOrder(): bool
    {
        return $this->type === 'meter_order';
    }

    public function isMeterElectricityOrder(): bool
    {
        return $this->type === 'meter_electricity_order';
    }

    public function isProductOrder(): bool
    {
        return $this->type === 'product_order';
    }
}
