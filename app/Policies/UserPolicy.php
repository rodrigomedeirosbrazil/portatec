<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('view_any_user');
    }

    public function view(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('view_user');
    }

    public function create(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('create_user');
    }

    public function update(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('update_user');
    }

    public function delete(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('delete_user');
    }

    public function restore(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('restore_user');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('force_delete_user');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('force_delete_any_user');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('restore_any_user');
    }

    public function replicate(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('replicate_user');
    }

    public function reorder(AuthUser $authUser): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return $authUser->can('reorder_user');
    }

}
