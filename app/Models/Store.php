<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'store_name',
        'platform',
        'credentials',
        'settings',
        'active'
    ];

    protected $casts = [
        'credentials' => 'array',
        'settings' => 'array',
        'active' => 'boolean'
    ];

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'store_team');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'store_user')->withPivot('permission_level');
    }
}
