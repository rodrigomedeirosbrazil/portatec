<?php

namespace App\Models;

use App\Enums\DeviceTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceFunction extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'type',
        'pin',
        'status',
    ];

    protected $casts = [
        'type' => DeviceTypeEnum::class,
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
