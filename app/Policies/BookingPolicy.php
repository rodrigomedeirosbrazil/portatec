<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $this->hasPlaceAccess($user, $booking->place_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->hasPlaceAccess($user, $booking->place_id);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $this->hasPlaceAccess($user, $booking->place_id);
    }

    public function restore(User $user, Booking $booking): bool
    {
        return $this->hasPlaceAccess($user, $booking->place_id);
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }

    public function replicate(User $user, Booking $booking): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function hasPlaceAccess(User $user, int $placeId): bool
    {
        return $user->placeUsers()
            ->where('place_id', $placeId)
            ->exists();
    }
}
