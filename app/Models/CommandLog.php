<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'place_id',
        'device_id',
        'command_type',
        'command_payload',
        'device_type',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the user that executed the command.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the place where the command was executed.
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Get the device that was controlled by the command.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
