<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessCode extends Model
{
    use HasFactory;

    protected $table = 'access_codes';

    protected $fillable = [
        'place_id',
        'user_id',
        'booking_id',
        'pin',
        'start',
        'end',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function deviceSyncs(): HasMany
    {
        return $this->hasMany(AccessCodeDeviceSync::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->booking) {
            return $this->booking->guest_name ?: "Reserva #{$this->booking->id}";
        }

        return 'Código manual';
    }

    /**
     * Verifica se o AccessCode está válido (dentro do período start-end)
     */
    public function isValid(): bool
    {
        $now = now();

        if ($this->end === null) {
            return $now->gte($this->start);
        }

        return $now->gte($this->start) && $now->lte($this->end);
    }
}
