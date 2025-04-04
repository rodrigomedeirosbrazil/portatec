<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TuyaDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tuya_id',
        'device_id',
        'local_key',
        'category',
    ];

    public function tuya(): BelongsTo
    {
        return $this->belongsTo(Tuya::class, 'tuya_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
