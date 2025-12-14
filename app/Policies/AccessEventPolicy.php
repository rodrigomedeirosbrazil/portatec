<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AccessEvent;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessEventPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_access::event');
    }

    public function view(AuthUser $authUser, AccessEvent $accessEvent): bool
    {
        return $authUser->can('view_access::event');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_access::event');
    }

    public function update(AuthUser $authUser, AccessEvent $accessEvent): bool
    {
        return $authUser->can('update_access::event');
    }

    public function delete(AuthUser $authUser, AccessEvent $accessEvent): bool
    {
        return $authUser->can('delete_access::event');
    }

    public function restore(AuthUser $authUser, AccessEvent $accessEvent): bool
    {
        return $authUser->can('restore_access::event');
    }

    public function forceDelete(AuthUser $authUser, AccessEvent $accessEvent): bool
    {
        return $authUser->can('force_delete_access::event');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_access::event');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_access::event');
    }

    public function replicate(AuthUser $authUser, AccessEvent $accessEvent): bool
    {
        return $authUser->can('replicate_access::event');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_access::event');
    }

}