<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TuyaAccount extends Model
{
    protected $fillable = [
        'user_id',
        'uid',
        'access_token',
        'refresh_token',
        'expires_at',
        'platform_url',
        'active',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tuyaDevices(): HasMany
    {
        return $this->hasMany(TuyaDevice::class);
    }

    public function needsRefresh(): bool
    {
        return $this->expires_at->lt(now()->addMinutes(5));
    }
}
