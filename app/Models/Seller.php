<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_code',
        'seller_name',
        'contact_info',
        'business_info',
        'stats',
        'active'
    ];

    protected $casts = [
        'contact_info' => 'array',
        'business_info' => 'array',
        'stats' => 'array',
        'active' => 'boolean'
    ];
}
