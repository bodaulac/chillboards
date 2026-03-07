<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'leader_id',
        'settings',
        'product_sheet_url',
        'can_view_trending',
        'active',
    ];

    protected $casts = [
        'settings' => 'array',
        'can_view_trending' => 'boolean',
        'active' => 'boolean',
    ];

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members()
    {
        return $this->hasMany(User::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_team');
    }
}
