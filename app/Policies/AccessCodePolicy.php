<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AccessCode;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessCodePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_access::code');
    }

    public function view(AuthUser $authUser, AccessCode $accessCode): bool
    {
        return $authUser->can('view_access::code');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_access::code');
    }

    public function update(AuthUser $authUser, AccessCode $accessCode): bool
    {
        return $authUser->can('update_access::code');
    }

    public function delete(AuthUser $authUser, AccessCode $accessCode): bool
    {
        return $authUser->can('delete_access::code');
    }

    public function restore(AuthUser $authUser, AccessCode $accessCode): bool
    {
        return $authUser->can('restore_access::code');
    }

    public function forceDelete(AuthUser $authUser, AccessCode $accessCode): bool
    {
        return $authUser->can('force_delete_access::code');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_access::code');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_access::code');
    }

    public function replicate(AuthUser $authUser, AccessCode $accessCode): bool
    {
        return $authUser->can('replicate_access::code');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_access::code');
    }
}
