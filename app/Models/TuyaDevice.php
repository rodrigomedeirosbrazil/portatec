<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuyaDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tuya_account_id',
        'place_id',
        'device_id',
        'name',
        'category',
        'online',
        'status',
        'enabled',
    ];

    protected $casts = [
        'online' => 'boolean',
        'status' => 'array',
        'enabled' => 'boolean',
    ];

    public function tuyaAccount(): BelongsTo
    {
        return $this->belongsTo(TuyaAccount::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
