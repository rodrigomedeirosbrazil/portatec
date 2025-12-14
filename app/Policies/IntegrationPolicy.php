<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Integration;
use Illuminate\Foundation\Auth\User as AuthUser;
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
        // Usuários podem ver apenas suas próprias integrations (ou se forem super_admin)
        return $authUser->can('view_integration') &&
            ($authUser->hasRole('super_admin') || $integration->user_id === $authUser->id);
    }

    public function create(AuthUser $authUser): bool
    {
        // Usuários podem criar suas próprias integrations
        return $authUser->can('create_integration');
    }

    public function update(AuthUser $authUser, Integration $integration): bool
    {
        // Usuários podem editar apenas suas próprias integrations
        return $authUser->can('update_integration') &&
            ($authUser->hasRole('super_admin') || $integration->user_id === $authUser->id);
    }

    public function delete(AuthUser $authUser, Integration $integration): bool
    {
        // Usuários podem deletar apenas suas próprias integrations
        return $authUser->can('delete_integration') &&
            ($authUser->hasRole('super_admin') || $integration->user_id === $authUser->id);
    }

    public function restore(AuthUser $authUser, Integration $integration): bool
    {
        return $authUser->can('restore_integration') &&
            ($authUser->hasRole('super_admin') || $integration->user_id === $authUser->id);
    }

    public function forceDelete(AuthUser $authUser, Integration $integration): bool
    {
        return $authUser->can('force_delete_integration') &&
            ($authUser->hasRole('super_admin') || $integration->user_id === $authUser->id);
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
        return $authUser->can('replicate_integration') &&
            ($authUser->hasRole('super_admin') || $integration->user_id === $authUser->id);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_integration');
    }
}
