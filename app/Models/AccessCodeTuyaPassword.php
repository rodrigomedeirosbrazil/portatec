<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessCodeTuyaPassword extends Model
{
    protected $table = 'access_code_tuya_passwords';

    protected $fillable = [
        'access_code_id',
        'device_id',
        'tuya_password_id',
    ];

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
