<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuyaCredential extends Model
{
    protected $table = 'tuya_credentials';

    protected $fillable = [
        'place_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'uid',
        'region',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function isExpiredOrExpiringSoon(int $bufferSeconds = 300): bool
    {
        return $this->expires_at->subSeconds($bufferSeconds)->isPast();
    }
}
