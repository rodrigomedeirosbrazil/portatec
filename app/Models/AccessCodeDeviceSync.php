<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessCodeDeviceSync extends Model
{
    protected $fillable = [
        'access_code_id',
        'device_id',
        'provider',
        'external_reference',
        'synced_start',
        'synced_end',
        'synced_pin',
        'last_synced_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'synced_start' => 'datetime',
        'synced_end' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
