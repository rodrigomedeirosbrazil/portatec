<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceDeviceFunction extends Model
{
    protected $fillable = [
        'place_id',
        'device_function_id',
    ];

    protected $casts = [

    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function deviceFunction(): BelongsTo
    {
        return $this->belongsTo(DeviceFunction::class);
    }
}
