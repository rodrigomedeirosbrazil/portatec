<?php

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum PropertyRoleEnum: string
{
    use Valuable;

    case Admin = 'admin';
    case Host = 'host';
    case Guest = 'guest';
}
