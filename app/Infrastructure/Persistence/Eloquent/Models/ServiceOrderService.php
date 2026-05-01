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
        'started_user_id',
        'finished_user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'started_user_id' => 'integer',
        'finished_user_id' => 'integer',
    ];
}
