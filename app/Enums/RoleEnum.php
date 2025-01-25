<?php

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum RoleEnum: string
{
    use Valuable;

    case Admin = 'admin';
    case Host = 'host';
    case guest = 'guest';
}
