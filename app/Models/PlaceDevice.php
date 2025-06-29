<?php

namespace App\Models;

use App\Enums\DeviceTypeEnum;
use App\Enums\DeviceStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceDevice extends Model
{
    protected $fillable = [
        'place_id',
        'device_id',
        'gpio',
        'type',
        'status',
    ];

    protected $casts = [
        'type' => DeviceTypeEnum::class,
        'status' => DeviceStatusEnum::class,
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
