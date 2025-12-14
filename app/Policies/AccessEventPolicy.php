<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccessEvent;
use Illuminate\Foundation\Auth\User as AuthUser;
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
        // Usuários podem ver eventos de Places que têm acesso
        return $authUser->can('view_access::event') &&
            ($authUser->hasRole('super_admin') ||
            $accessEvent->device->place->placeUsers()
                ->where('user_id', $authUser->id)
                ->exists());
    }

    public function create(AuthUser $authUser): bool
    {
        // Somente leitura - eventos são criados automaticamente
        return false;
    }

    public function update(AuthUser $authUser, AccessEvent $accessEvent): bool
    {
        // Somente leitura
        return false;
    }

    public function delete(AuthUser $authUser, AccessEvent $accessEvent): bool
    {
        // Somente leitura
        return false;
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
