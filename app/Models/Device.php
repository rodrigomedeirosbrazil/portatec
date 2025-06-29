<?php

namespace App\Models;

use App\Enums\DeviceTypeEnum;
use App\Events\DeviceCreatedEvent;
use App\Events\DeviceDeletedEvent;
use App\Events\DeviceUpdatedEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'chip_id',
        'last_sync',
        'status',
    ];

    protected $casts = [
        'last_sync' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Device $device) {
            event(new DeviceCreatedEvent($device->id));
        });

        static::updated(function (Device $device) {
            $changes = $device->getChanges(); // Campos modificados e novos valores
            event(new DeviceUpdatedEvent($device->id, $changes));
        });

        static::deleted(function (Device $device) {
            event(new DeviceDeletedEvent($device->id));
        });
    }

    public function placeDevices(): HasMany
    {
        return $this->hasMany(PlaceDevice::class);
    }

    public function functions(): HasMany
    {
        return $this->hasMany(DeviceFunction::class);
    }

    public function isAvailable(): bool
    {
        return $this->last_sync ? $this->last_sync->diffInMinutes(now()) < 10 : false;
    }
}
