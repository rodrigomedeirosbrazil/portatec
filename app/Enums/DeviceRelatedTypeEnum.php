<?php

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum DeviceRelatedTypeEnum: string
{
    use Valuable;

    case Mqtt = 'mqtt';
    case Tuya = 'tuya';

    public function label(): string
    {
        return match($this) {
            self::Mqtt => 'Mqtt',
            self::Tuya => 'Tuya',
        };
    }

    public static function toArray(): array
    {
        return [
            self::Mqtt->value => 'Mqtt',
            self::Tuya->value => 'Tuya',
        ];
    }
}
