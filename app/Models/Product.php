<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'base_sku',
        'title',
        'seller_code',
        'platform_skus',
        'walmart_data',
        'upload_status',
        'design_assignment',
    ];

    protected $casts = [
        'platform_skus' => 'array',
        'walmart_data' => 'array',
        'upload_status' => 'array',
        'design_assignment' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'product_details->sku', 'sku');
    }
}
