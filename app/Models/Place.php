<?php

namespace App\Models;

use App\Enums\PlaceRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'role',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'role' => PlaceRoleEnum::class,
    ];

    public function placeUsers(): HasMany
    {
        return $this->hasMany(PlaceUser::class);
    }

    public function placeDevices(): HasMany
    {
        return $this->hasMany(PlaceDevice::class);
    }

    public function hasAccessToPlace(User $user): bool
    {
        return $this->placeUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', [PlaceRoleEnum::Admin, PlaceRoleEnum::Host])
            ->exists();
    }
}
