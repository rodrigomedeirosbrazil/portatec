<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Integration;
use Illuminate\Auth\Access\HandlesAuthorization;

class IntegrationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_integration');
    }

    public function view(AuthUser $authUser, Integration $integration): bool
    {
        return $authUser->can('view_integration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_integration');
    }

    public function update(AuthUser $authUser, Integration $integration): bool
    {
        return $authUser->can('update_integration');
    }

    public function delete(AuthUser $authUser, Integration $integration): bool
    {
        return $authUser->can('delete_integration');
    }

    public function restore(AuthUser $authUser, Integration $integration): bool
    {
        return $authUser->can('restore_integration');
    }

    public function forceDelete(AuthUser $authUser, Integration $integration): bool
    {
        return $authUser->can('force_delete_integration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_integration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_integration');
    }

    public function replicate(AuthUser $authUser, Integration $integration): bool
    {
        return $authUser->can('replicate_integration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_integration');
    }

}