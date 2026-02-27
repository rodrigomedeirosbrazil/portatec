<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingDeletionReasonEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'place_id',
        'integration_id',
        'guest_name',
        'check_in',
        'check_out',
        'source',
        'external_id',
        'deletion_reason',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'deletion_reason' => BookingDeletionReasonEnum::class,
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function accessCode(): HasOne
    {
        return $this->hasOne(AccessCode::class);
    }
}
