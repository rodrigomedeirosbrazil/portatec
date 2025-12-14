<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Platform;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlatformPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        // Todos os usuários podem ver platforms (são entidades do sistema)
        return $authUser->can('view_any_platform');
    }

    public function view(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('view_platform');
    }

    public function create(AuthUser $authUser): bool
    {
        // Apenas super_admin pode criar platforms
        return $authUser->can('create_platform') && $authUser->hasRole('super_admin');
    }

    public function update(AuthUser $authUser, Platform $platform): bool
    {
        // Apenas super_admin pode editar platforms
        return $authUser->can('update_platform') && $authUser->hasRole('super_admin');
    }

    public function delete(AuthUser $authUser, Platform $platform): bool
    {
        // Apenas super_admin pode deletar platforms
        return $authUser->can('delete_platform') && $authUser->hasRole('super_admin');
    }

    public function restore(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('restore_platform') && $authUser->hasRole('super_admin');
    }

    public function forceDelete(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('force_delete_platform') && $authUser->hasRole('super_admin');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_platform') && $authUser->hasRole('super_admin');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_platform') && $authUser->hasRole('super_admin');
    }

    public function replicate(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('replicate_platform') && $authUser->hasRole('super_admin');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_platform');
    }
}
