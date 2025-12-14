<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceBrandEnum;
use App\Enums\DeviceTypeEnum;
use App\Events\DeviceCreatedEvent;
use App\Events\DeviceDeletedEvent;
use App\Events\DeviceUpdatedEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'external_device_id', // ID externo do dispositivo (Portatec ou Tuya)
        'place_id',
        'brand',
        'default_pin',
        'last_sync',
    ];

    protected $casts = [
        'brand' => DeviceBrandEnum::class,
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

    public function placeDeviceFunctions(): HasMany
    {
        return $this->hasMany(PlaceDeviceFunction::class);
    }

    public function deviceFunctions(): HasMany
    {
        return $this->hasMany(DeviceFunction::class);
    }

    public function isAvailable(): bool
    {
        return $this->last_sync ? $this->last_sync->diffInMinutes(now()) < 10 : false;
    }

    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    // Método helper para obter funções por tipo
    public function getFunctionByType(DeviceTypeEnum $type): ?DeviceFunction
    {
        return $this->deviceFunctions()->where('type', $type)->first();
    }
}
