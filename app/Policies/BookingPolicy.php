<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlaceRoleEnum;
use App\Models\Booking;
use Illuminate\Foundation\Auth\User as AuthUser;
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
        // Usuários podem ver bookings de Places que têm acesso
        return $authUser->can('view_booking') &&
            ($authUser->hasRole('super_admin') ||
            $booking->place->placeUsers()
                ->where('user_id', $authUser->id)
                ->exists());
    }

    public function create(AuthUser $authUser): bool
    {
        // Apenas Admin/Owner podem criar
        return $authUser->can('create_booking');
    }

    public function update(AuthUser $authUser, Booking $booking): bool
    {
        // Apenas Admin/Owner podem editar
        return $authUser->can('update_booking') &&
            ($authUser->hasRole('super_admin') ||
            $booking->place->placeUsers()
                ->where('user_id', $authUser->id)
                ->whereIn('role', [PlaceRoleEnum::Admin])
                ->exists());
    }

    public function delete(AuthUser $authUser, Booking $booking): bool
    {
        // Apenas Admin/Owner podem deletar
        return $authUser->can('delete_booking') &&
            ($authUser->hasRole('super_admin') ||
            $booking->place->placeUsers()
                ->where('user_id', $authUser->id)
                ->whereIn('role', [PlaceRoleEnum::Admin])
                ->exists());
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
