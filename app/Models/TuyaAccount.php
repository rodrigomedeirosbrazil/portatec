<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TuyaAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_code',
        'uid',
        'access_token',
        'refresh_token',
        'token_info',
        'terminal_id',
        'endpoint',
        'expires_at',
        'platform_url',
        'active',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'user_code' => 'encrypted',
        'token_info' => 'array',
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
