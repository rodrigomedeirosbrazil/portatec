<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Spec §5. A fonte da verdade é `device_user`:
 *
 * - `admin`  — configura, concede, revoga, transfere. Um por dispositivo.
 * - `user`   — concessão de uso: pode acionar e levar para os locais que administra.
 *
 * Membro de local que contém o dispositivo aciona, mas não configura nem leva
 * para outro lugar. É o que separa "morador usa o portão" de "morador manda no
 * portão".
 */
class DevicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Device $device): bool
    {
        return $device->isUsableBy($user) || $this->isPlaceMember($user, $device);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Device $device): bool
    {
        return $device->isAdministeredBy($user);
    }

    public function delete(User $user, Device $device): bool
    {
        return $device->isAdministeredBy($user);
    }

    /** Levar o dispositivo para um local. O local de destino é checado à parte. */
    public function attach(User $user, Device $device): bool
    {
        return $device->isUsableBy($user);
    }

    /** Conceder, revogar e transferir. */
    public function managePermissions(User $user, Device $device): bool
    {
        return $device->isAdministeredBy($user);
    }

    public function restore(User $user, Device $device): bool
    {
        return $device->isAdministeredBy($user);
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
        return false;
    }

    public function replicate(User $user, Device $device): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function isPlaceMember(User $user, Device $device): bool
    {
        if ($device->places()->whereHas('placeUsers', fn ($query) => $query->where('user_id', $user->id))->exists()) {
            return true;
        }

        return $device->place_id !== null
            && $user->placeUsers()->where('place_id', $device->place_id)->exists();
    }
}
