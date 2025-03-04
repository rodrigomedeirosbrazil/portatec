<?php

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum PlaceRoleEnum: string
{
    use Valuable;

    case Admin = 'admin';
    case Host = 'host';

    public function label(): string
    {
        return match($this) {
            self::Admin => __('app.place_roles.admin'),
            self::Host => __('app.place_roles.host'),
        };
    }

    public static function toArray(): array
    {
        return [
            self::Admin->value => __('app.place_roles.admin'),
            self::Host->value => __('app.place_roles.host'),
        ];
    }
}
