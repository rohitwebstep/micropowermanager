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
        'price',               
        'emi_months',            
        'installment_amount',    
        'plan_start_date',       
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
        $related = new BluettiDeviceTransaction();
        $related->setConnection('mysql');

        return $this->hasMany($related::class, 'device_id')
            ->setModel($related);
    }
}
