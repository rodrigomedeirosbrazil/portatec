<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessCode extends Model
{
    use HasFactory;

    protected $table = 'access_codes';

    protected $fillable = [
        'place_id',
        'user_id', // Agora nullable
        'booking_id', // Novo campo (será adicionado em migration futura)
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

    /**
     * Verifica se o AccessCode está válido (dentro do período start-end)
     */
    public function isValid(): bool
    {
        $now = now();
        return $now->gte($this->start) && $now->lte($this->end);
    }
}
