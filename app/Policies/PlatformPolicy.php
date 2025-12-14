<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Platform;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlatformPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_platform');
    }

    public function view(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('view_platform');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_platform');
    }

    public function update(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('update_platform');
    }

    public function delete(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('delete_platform');
    }

    public function restore(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('restore_platform');
    }

    public function forceDelete(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('force_delete_platform');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_platform');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_platform');
    }

    public function replicate(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('replicate_platform');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_platform');
    }

}