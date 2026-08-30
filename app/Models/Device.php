<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceBrandEnum;
use App\Enums\DeviceTypeEnum;
use App\Events\DeviceCreatedEvent;
use App\Events\DeviceDeletedEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'external_device_id', // ID externo do dispositivo (Portatec ou Tuya)
        'place_id',
        'integration_id',
        'brand',
        'default_pin',
        'last_sync',
        'wifi_strength',
        'firmware_version',
        'tuya_category',
        'tuya_product_id',
        'tuya_product_name',
        'tuya_icon',
        'tuya_online',
        'tuya_status_payload',
        'tuya_functions',
    ];

    protected $casts = [
        'brand' => DeviceBrandEnum::class,
        'last_sync' => 'datetime',
        'tuya_online' => 'boolean',
        'tuya_status_payload' => 'array',
        'tuya_functions' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (Device $device) {
            event(new DeviceCreatedEvent($device->id));
        });

        static::deleted(function (Device $device) {
            event(new DeviceDeletedEvent($device->id));
        });
    }

    public function placeDeviceFunctions(): HasManyThrough
    {
        return $this->hasManyThrough(
            PlaceDeviceFunction::class,
            DeviceFunction::class,
            'device_id',
            'device_function_id',
            'id',
            'id'
        );
    }

    public function deviceFunctions(): HasMany
    {
        return $this->hasMany(DeviceFunction::class);
    }

    public function isAvailable(): bool
    {
        if ($this->brand === DeviceBrandEnum::Tuya) {
            return (bool) ($this->tuya_online ?? false);
        }

        return $this->last_sync ? $this->last_sync->diffInMinutes(now()) < 10 : false;
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }

    public function accessCodeDeviceSyncs(): HasMany
    {
        return $this->hasMany(AccessCodeDeviceSync::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'device_place')->withTimestamps();
    }

    // Método helper para obter funções por tipo
    public function getFunctionByType(DeviceTypeEnum $type): ?DeviceFunction
    {
        return $this->deviceFunctions()->where('type', $type)->first();
    }

    /** Retorna a função de sensor usada para exibir status (primeiro sensor do dispositivo). */
    public function getStatusFunction(): ?DeviceFunction
    {
        return $this->deviceFunctions()->where('type', DeviceTypeEnum::Sensor)->first();
    }

    /**
     * Categorias Tuya que representam fechadura (smart lock).
     *
     * @see https://developer.tuya.com/en/docs/iot/lock
     */
    private const TUYA_LOCK_CATEGORIES = ['ms', 'jtmspro', 'jtmsbh', 'mk'];

    private const TUYA_TEMPORARY_PASSWORD_DP = 'temporary_password_creat';

    /** Fechadura Tuya: exige categoria conhecida — categoria nula significa "ainda não sincronizado". */
    public function isTuyaLock(): bool
    {
        return $this->brand === DeviceBrandEnum::Tuya
            && in_array($this->tuya_category, self::TUYA_LOCK_CATEGORIES, true);
    }

    /**
     * Só manda PIN para fechadura que declarou o DP na resposta de /specifications.
     * Fechadura Wi-Fi (jtmspro) costuma expor unlock_method_create em vez deste DP.
     */
    public function supportsTuyaTemporaryPassword(): bool
    {
        return $this->isTuyaLock()
            && in_array(self::TUYA_TEMPORARY_PASSWORD_DP, $this->tuya_functions ?? [], true);
    }

    public function supportsPlaceAccessCodes(): bool
    {
        return $this->brand === DeviceBrandEnum::Portatec
            || $this->supportsTuyaTemporaryPassword();
    }
}
