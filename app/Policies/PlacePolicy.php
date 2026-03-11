<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlaceRoleEnum;
use App\Models\Place;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlacePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Place $place): bool
    {
        return $this->hasPlaceAccess($user, $place->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Place $place): bool
    {
        return $this->hasPlaceAccess($user, $place->id);
    }

    public function delete(User $user, Place $place): bool
    {
        return $this->hasPlaceAccess($user, $place->id);
    }

    public function restore(User $user, Place $place): bool
    {
        return $this->hasPlaceAccess($user, $place->id);
    }

    public function forceDelete(User $user, Place $place): bool
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

    public function replicate(User $user, Place $place): bool
    {
        return $this->hasPlaceAdminAccess($user, $place->id);
    }

    public function manageMembers(User $user, Place $place): bool
    {
        return $this->hasPlaceAdminAccess($user, $place->id);
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

    private function hasPlaceAdminAccess(User $user, int $placeId): bool
    {
        return $user->placeUsers()
            ->where('place_id', $placeId)
            ->where('role', PlaceRoleEnum::Admin)
            ->exists();
    }
}
