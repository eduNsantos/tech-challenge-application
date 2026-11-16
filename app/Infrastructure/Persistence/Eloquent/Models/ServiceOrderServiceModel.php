<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderServiceModel extends Model
{
    protected $table = 'service_order_services';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'service_order_id',
        'service_id',
        'quantity',
        'price',
        'started_at',
        'started_user_id',
        'finished_at',
        'finished_user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function service()
    {
        return $this->belongsTo(ServiceModel::class, 'service_id');
    }

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrderModel::class, 'service_order_id');
    }
}
