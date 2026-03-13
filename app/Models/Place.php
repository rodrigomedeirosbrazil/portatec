<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Place extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    public function tuyaCredential(): HasOne
    {
        return $this->hasOne(TuyaCredential::class);
    }

    public function getValidAccessCodes()
    {
        return $this->accessCodes()
            ->where('start', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end')
                    ->orWhere('end', '>=', now());
            })
            ->get();
    }

    public function hasAccessToPlace(User $user): bool
    {
        return $this->placeUsers()
            ->where('user_id', $user->id)
            ->exists();
    }
}
