<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderItem extends Model
{
    protected $table = 'service_order_items';
    protected $fillable = [
        'id',
        'service_order_id',
        'item_id',
        'quantity',
        'price'
    ];
}
