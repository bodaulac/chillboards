<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trend extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'description',
        'keywords',
        'source',
        'trending_score',
        'potential_designs',
        'status',
        'priority',
        'metadata'
    ];

    protected $casts = [
        'keywords' => 'array',
        'potential_designs' => 'array',
        'metadata' => 'array',
        'trending_score' => 'float'
    ];
}
