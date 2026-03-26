<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleModel extends Model
{
    protected $table = 'vehicles';

    protected $fillable = [
        'brand',
        'model',
        'year',
        'plate'
    ];
}