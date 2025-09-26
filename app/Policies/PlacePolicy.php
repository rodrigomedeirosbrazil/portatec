<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Place;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlacePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_place');
    }

    public function view(AuthUser $authUser, Place $place): bool
    {
        return $authUser->can('view_place');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_place');
    }

    public function update(AuthUser $authUser, Place $place): bool
    {
        return $authUser->can('update_place');
    }

    public function delete(AuthUser $authUser, Place $place): bool
    {
        return $authUser->can('delete_place');
    }

    public function restore(AuthUser $authUser, Place $place): bool
    {
        return $authUser->can('restore_place');
    }

    public function forceDelete(AuthUser $authUser, Place $place): bool
    {
        return $authUser->can('force_delete_place');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_place');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_place');
    }

    public function replicate(AuthUser $authUser, Place $place): bool
    {
        return $authUser->can('replicate_place');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_place');
    }

}