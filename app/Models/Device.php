<?php

namespace App\Models;

use App\Enums\DeviceRelatedTypeEnum;
use App\Enums\DeviceTypeEnum;
use App\Events\DeviceCreatedEvent;
use App\Events\DeviceDeletedEvent;
use App\Events\DeviceUpdatedEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'device_type',
        'status',
    ];

    protected $casts = [
        'type' => DeviceTypeEnum::class,
        'device_type' => DeviceRelatedTypeEnum::class,
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function placeDevices(): HasMany
    {
        return $this->hasMany(PlaceDevice::class);
    }

    public function mqttDevice(): HasOne
    {
        return $this->hasOne(MqttDevice::class);
    }

    public function tuyaDevice(): HasOne
    {
        return $this->hasOne(TuyaDevice::class);
    }
}
