<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum DeviceRoleEnum: string
{
    use Valuable;

    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('app.device_roles.admin'),
            self::User => __('app.device_roles.user'),
        };
    }

    public static function toArray(): array
    {
        return [
            self::Admin->value => __('app.device_roles.admin'),
            self::User->value => __('app.device_roles.user'),
        ];
    }
}
