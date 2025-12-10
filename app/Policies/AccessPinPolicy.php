<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AccessPin;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessPinPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_access::pin');
    }

    public function view(AuthUser $authUser, AccessPin $accessPin): bool
    {
        return $authUser->can('view_access::pin');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_access::pin');
    }

    public function update(AuthUser $authUser, AccessPin $accessPin): bool
    {
        return $authUser->can('update_access::pin');
    }

    public function delete(AuthUser $authUser, AccessPin $accessPin): bool
    {
        return $authUser->can('delete_access::pin');
    }

    public function restore(AuthUser $authUser, AccessPin $accessPin): bool
    {
        return $authUser->can('restore_access::pin');
    }

    public function forceDelete(AuthUser $authUser, AccessPin $accessPin): bool
    {
        return $authUser->can('force_delete_access::pin');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_access::pin');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_access::pin');
    }

    public function replicate(AuthUser $authUser, AccessPin $accessPin): bool
    {
        return $authUser->can('replicate_access::pin');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_access::pin');
    }

}