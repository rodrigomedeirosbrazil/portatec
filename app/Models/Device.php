<?php

namespace App\Models;

use App\Enums\DeviceTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'topic',
        'command_topic',
        'payload_on',
        'payload_off',
        'availability_topic',
        'availability_payload_on',
        'is_available',
        'json_attribute',
        'status',
    ];

    protected $casts = [
        'type' => DeviceTypeEnum::class,
        'is_available' => 'boolean',
    ];

    public function placeDevices(): HasMany
    {
        return $this->hasMany(PlaceDevice::class);
    }
}
