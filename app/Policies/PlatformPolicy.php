<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Platform;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlatformPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Platform $platform): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Platform $platform): bool
    {
        return true;
    }

    public function delete(User $user, Platform $platform): bool
    {
        return false;
    }

    public function restore(User $user, Platform $platform): bool
    {
        return false;
    }

    public function forceDelete(User $user, Platform $platform): bool
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

    public function replicate(User $user, Platform $platform): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
