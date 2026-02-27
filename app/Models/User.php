<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    /**
     * Transitional compatibility helper while role system is removed.
     */
    public function hasRole(string $role): bool
    {
        return $role === 'super_admin';
    }
}
