<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingDeletionReasonEnum: string
{
    case ChangeDate = 'change_date';
    case Canceled = 'canceled';
    case CanceledByUser = 'canceled_by_user';
    case ChangeGuest = 'change_guest';
    case Other = 'other';
}
