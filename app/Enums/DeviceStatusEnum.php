<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum DeviceStatusEnum: string
{
    use Valuable;

    case Open = 'open';
    case Closed = 'closed';
    case On = 'on';
    case Off = 'off';

    public function label(): string
    {
        return match($this) {
            self::Open => __('app.device_statuses.open'),
            self::Closed => __('app.device_statuses.closed'),
            self::On => __('app.device_statuses.on'),
            self::Off => __('app.device_statuses.off'),
        };
    }

    public static function toArray(): array
    {
        return [
            self::Open->value => __('app.device_statuses.open'),
            self::Closed->value => __('app.device_statuses.closed'),
            self::On->value => __('app.device_statuses.on'),
            self::Off->value => __('app.device_statuses.off'),
        ];
    }
}
