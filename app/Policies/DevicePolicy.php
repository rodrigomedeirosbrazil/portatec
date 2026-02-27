<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DevicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Device $device): bool
    {
        return $device->place_id !== null && $this->hasPlaceAccess($user, $device->place_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Device $device): bool
    {
        return $device->place_id !== null && $this->hasPlaceAccess($user, $device->place_id);
    }

    public function delete(User $user, Device $device): bool
    {
        return $device->place_id !== null && $this->hasPlaceAccess($user, $device->place_id);
    }

    public function restore(User $user, Device $device): bool
    {
        return $device->place_id !== null && $this->hasPlaceAccess($user, $device->place_id);
    }

    public function forceDelete(User $user, Device $device): bool
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

    public function replicate(User $user, Device $device): bool
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
