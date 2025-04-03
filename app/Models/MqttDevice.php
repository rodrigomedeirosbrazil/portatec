<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MqttDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'topic',
        'command_topic',
        'payload_on',
        'payload_off',
        'availability_topic',
        'availability_payload_on',
        'is_available',
        'json_attribute',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
