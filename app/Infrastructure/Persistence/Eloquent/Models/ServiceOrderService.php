<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderService extends Model
{
    protected $table = 'service_order_services';

    protected $fillable = [
        'id',
        'service_order_id',
        'service_id',
        'quantity',
        'price',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
