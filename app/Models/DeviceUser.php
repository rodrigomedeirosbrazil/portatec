<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUser extends Model
{
    protected $table = 'device_user';

    protected $fillable = [
        'device_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => DeviceRoleEnum::class,
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
