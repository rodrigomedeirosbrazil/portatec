<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class IntegrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Integration $integration): bool
    {
        return (int) $integration->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Integration $integration): bool
    {
        return (int) $integration->user_id === (int) $user->id;
    }

    public function delete(User $user, Integration $integration): bool
    {
        return (int) $integration->user_id === (int) $user->id;
    }

    public function restore(User $user, Integration $integration): bool
    {
        return (int) $integration->user_id === (int) $user->id;
    }

    public function forceDelete(User $user, Integration $integration): bool
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

    public function replicate(User $user, Integration $integration): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
