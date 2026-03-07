<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'description',
        'event_type',
        'source',
        'category',
        'priority',
        'related_sku',
        'action_required',
        'action_type',
        'event_timestamp',
        'metadata',
        'is_read'
    ];

    protected $casts = [
        'metadata' => 'array',
        'action_required' => 'boolean',
        'is_read' => 'boolean',
        'event_timestamp' => 'datetime'
    ];
}
