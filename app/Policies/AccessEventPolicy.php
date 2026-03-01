<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccessEvent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AccessEvent $accessEvent): bool
    {
        $placeId = $accessEvent->device?->place_id;

        return $placeId !== null && $this->hasPlaceAccess($user, $placeId);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function delete(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function restore(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function forceDelete(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, AccessEvent $accessEvent): bool
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
