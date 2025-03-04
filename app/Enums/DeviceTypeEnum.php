<?php

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum DeviceTypeEnum: string
{
    use Valuable;

    case Switch = 'switch';
    case Sensor = 'sensor';
    case Button = 'button';

    public function label(): string
    {
        return match($this) {
            self::Switch => __('app.device_types.switch'),
            self::Sensor => __('app.device_types.sensor'),
            self::Button => __('app.device_types.button'),
        };
    }

    public static function toArray(): array
    {
        return [
            self::Switch->value => __('app.device_types.switch'),
            self::Sensor->value => __('app.device_types.sensor'),
            self::Button->value => __('app.device_types.button'),
        ];
    }
}
