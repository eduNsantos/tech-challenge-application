<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderItemModel extends Model
{
    protected $table = 'service_order_items';
    protected $fillable = [
        'id',
        'service_order_id',
        'item_id',
        'quantity',
        'price'
    ];

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrderModel::class, 'service_order_id');
    }

    public function item()
    {
        return $this->belongsTo(ItemModel::class, 'item_id');
    }
}
