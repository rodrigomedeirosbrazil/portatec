<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CommandLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommandLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CommandLog $commandLog): bool
    {
        return $this->hasPlaceAccess($user, $commandLog->place_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CommandLog $commandLog): bool
    {
        return false;
    }

    public function delete(User $user, CommandLog $commandLog): bool
    {
        return false;
    }

    public function restore(User $user, CommandLog $commandLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, CommandLog $commandLog): bool
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

    public function replicate(User $user, CommandLog $commandLog): bool
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
