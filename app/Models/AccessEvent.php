<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessEvent extends Model
{
    protected $fillable = [
        'device_id',
        'access_code_id',
        'pin',
        'result',
        'device_timestamp',
        'server_timestamp',
        'metadata',
    ];

    protected $casts = [
        'device_timestamp' => 'datetime',
        'server_timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }
}
