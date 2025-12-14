<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Booking;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_booking');
    }

    public function view(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('view_booking');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_booking');
    }

    public function update(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('update_booking');
    }

    public function delete(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('delete_booking');
    }

    public function restore(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('restore_booking');
    }

    public function forceDelete(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('force_delete_booking');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_booking');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_booking');
    }

    public function replicate(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('replicate_booking');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_booking');
    }

}