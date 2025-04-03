<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tuya extends Model
{
    use HasFactory;

    protected $table = 'tuya';

    protected $fillable = [
        'device_id',
        'client_id',
        'client_secret',
        'uid',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function tuyaDevices(): HasMany
    {
        return $this->hasMany(TuyaDevice::class, 'tuya_id');
    }
}
