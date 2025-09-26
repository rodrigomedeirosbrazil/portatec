<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CommandLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommandLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_command::log');
    }

    public function view(AuthUser $authUser, CommandLog $commandLog): bool
    {
        return $authUser->can('view_command::log');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_command::log');
    }

    public function update(AuthUser $authUser, CommandLog $commandLog): bool
    {
        return $authUser->can('update_command::log');
    }

    public function delete(AuthUser $authUser, CommandLog $commandLog): bool
    {
        return $authUser->can('delete_command::log');
    }

    public function restore(AuthUser $authUser, CommandLog $commandLog): bool
    {
        return $authUser->can('restore_command::log');
    }

    public function forceDelete(AuthUser $authUser, CommandLog $commandLog): bool
    {
        return $authUser->can('force_delete_command::log');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_command::log');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_command::log');
    }

    public function replicate(AuthUser $authUser, CommandLog $commandLog): bool
    {
        return $authUser->can('replicate_command::log');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_command::log');
    }

}