<?php
namespace App\Models\Bluetti;

use App\Models\Base\BaseModel;

class BluettiDeviceTransaction extends BaseModel
{
    protected $connection = 'mysql';
    protected $table      = 'bluetti_device_transactions';

    protected $fillable = [
        'device_id',
        'transaction_id',
        'month',
        'year',
        'is_active',
        'code_serial_number',
        'token',
        'days_to_activate',
        'token_type',
        'request_code_response',
        'query_code_history_response',
    ];

    protected $casts = [
        'month'                        => 'integer',
        'year'                         => 'integer',
        'is_active'                    => 'boolean',
        'days_to_activate'             => 'integer',
        'token_type'                   => 'integer',
        'request_code_response'        => 'array',
        'query_code_history_response'  => 'array',
    ];

    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection('mysql');
    }

    public function device()
    {
        return $this->belongsTo(BluettiDevice::class, 'device_id');
    }
}