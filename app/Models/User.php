<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'team_id',
        'seller_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function leadingTeam()
    {
        return $this->hasOne(Team::class, 'leader_id');
    }

    public function assignedStores()
    {
        return $this->belongsToMany(Store::class, 'store_user')->withPivot('permission_level');
    }

    public function isAdmin() { return $this->role === 'admin'; }
    
    public function isLeader() { 
        // Check if user is leading any team (regardless of role)
        return $this->leadingTeam()->exists();
    }
    
    public function isTeamLeader() { 
        return $this->isLeader();
    }
    
    public function isSeller() { return $this->role === 'seller'; }
}
