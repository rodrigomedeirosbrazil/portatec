<?php

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum PlaceRoleEnum: string
{
    use Valuable;

    case Admin = 'admin';
    case Host = 'host';
    case Guest = 'guest';
}
