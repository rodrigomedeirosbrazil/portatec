<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlaceRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function placeDeviceFunctions(): HasMany
    {
        return $this->hasMany(PlaceDeviceFunction::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function accessCodes(): HasMany
    {
        return $this->hasMany(AccessCode::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function integrations(): BelongsToMany
    {
        return $this->belongsToMany(Integration::class, 'place_integration')
            ->withPivot('external_id')
            ->withTimestamps();
    }

    public function getValidAccessCodes()
    {
        return $this->accessCodes()
            ->where('start', '<=', now())
            ->where('end', '>=', now())
            ->get();
    }

    public function hasAccessToPlace(User $user): bool
    {
        return $this->placeUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', [PlaceRoleEnum::Admin, PlaceRoleEnum::Host])
            ->exists();
    }
}
