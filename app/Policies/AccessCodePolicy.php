<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccessCode;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessCodePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AccessCode $accessCode): bool
    {
        return $this->hasPlaceAccess($user, $accessCode->place_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AccessCode $accessCode): bool
    {
        return $this->hasPlaceAccess($user, $accessCode->place_id);
    }

    public function delete(User $user, AccessCode $accessCode): bool
    {
        return $this->hasPlaceAccess($user, $accessCode->place_id);
    }

    public function restore(User $user, AccessCode $accessCode): bool
    {
        return $this->hasPlaceAccess($user, $accessCode->place_id);
    }

    public function forceDelete(User $user, AccessCode $accessCode): bool
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

    public function replicate(User $user, AccessCode $accessCode): bool
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
