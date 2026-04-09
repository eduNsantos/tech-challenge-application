<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ItemModel extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'code',
        'type',
        'description',
        'measure_unit',
        'stock_quantity',
        'minimum_quantity',
        'unit_price',
        'created_user_id',
        'updated_user_id',
    ];
}
