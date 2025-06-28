<?php

namespace App\Models;

use App\Enums\DeviceTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceDevice extends Model
{
    protected $fillable = [
        'place_id',
        'device_id',
        'gpio',
        'type',
    ];

    protected $casts = [
        'type' => DeviceTypeEnum::class,
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
