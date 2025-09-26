<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Device;
use Illuminate\Auth\Access\HandlesAuthorization;

class DevicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_device');
    }

    public function view(AuthUser $authUser, Device $device): bool
    {
        return $authUser->can('view_device');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_device');
    }

    public function update(AuthUser $authUser, Device $device): bool
    {
        return $authUser->can('update_device');
    }

    public function delete(AuthUser $authUser, Device $device): bool
    {
        return $authUser->can('delete_device');
    }

    public function restore(AuthUser $authUser, Device $device): bool
    {
        return $authUser->can('restore_device');
    }

    public function forceDelete(AuthUser $authUser, Device $device): bool
    {
        return $authUser->can('force_delete_device');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_device');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_device');
    }

    public function replicate(AuthUser $authUser, Device $device): bool
    {
        return $authUser->can('replicate_device');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_device');
    }

}