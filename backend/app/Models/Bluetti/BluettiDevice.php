<?php
/*
    micropowermanager-main\backend\app\Models\Bluetti\BluettiDevice.php
*/
namespace App\Models\Bluetti;

use App\Models\Base\BaseModel;

class BluettiDevice extends BaseModel
{
    protected $connection = 'mysql';
    protected $table = 'bluetti_devices';

    protected $fillable = [
        'device_name',
        'serial_number',
        'client',
        'style',
        'created_date',
        'customer_id',
        'transaction_id',
        'customer_no',
    ];

    protected $casts = [
        'created_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(\App\Models\Person\Person::class, 'customer_id');
    }

    // ✅ NEW — Monthly transactions relationship
    public function transactions()
    {
        return $this->hasMany(
            BluettiDeviceTransaction::class,
            'device_id'
        );
    }
}
