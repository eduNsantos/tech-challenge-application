<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovementModel extends Model
{
    protected $table = 'stock_movements';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'item_id',
        'service_order_id',
        'movement_type',
        'quantity',
        'previous_quantity',
        'current_quantity',
        'reason',
        'notes',
        'created_user_id',
        'created_at',
    ];
}
