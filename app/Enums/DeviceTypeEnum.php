<?php

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum DeviceTypeEnum: string
{
    use Valuable;

    case Switch = 'switch';
    case Sensor = 'sensor';
    case Button = 'button';
}
