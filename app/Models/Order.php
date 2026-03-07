<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'platform_order_id',
        'platform',
        'store_id',
        'seller_code',
        'order_date',
        'status',
        'customer_details',
        'product_details',
        'workflow',
        'timeline',
        'tracking_info',
        'financials',
        'fulfillment',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'customer_details' => 'array',
        'product_details' => 'array',
        'workflow' => 'array',
        'timeline' => 'array',
        'tracking_info' => 'array',
        'financials' => 'array',
        'fulfillment' => 'array',
    ];

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'store_id');
    }
}
