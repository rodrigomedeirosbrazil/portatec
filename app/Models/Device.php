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
    ];

    protected $casts = [
        'type' => DeviceTypeEnum::class,
    ];

    public function placeDevices(): HasMany
    {
        return $this->hasMany(PlaceDevice::class);
    }
}
