<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrderModel extends Model
{
    use SoftDeletes;

    protected $table = 'service_orders';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'customer_id',
        'vehicle_id',
        'status',
        'services_total',
        'parts_total',
        'total_budget',
        'quote_sent_at',
        'quote_approved_at',
        'approval_token',
        'created_user_id',
        'updated_user_id'
    ];

    protected $casts = [
        'services_total' => 'float',
        'parts_total' => 'float',
        'total_budget' => 'float',
        'quote_sent_at' => 'datetime',
        'quote_approved_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_id');
    }

    public function services()
    {
        return $this->hasMany(ServiceOrderServiceModel::class, 'service_order_id');
    }

    public function items()
    {
        return $this->hasMany(ServiceOrderItemModel::class, 'service_order_id');
    }
}
