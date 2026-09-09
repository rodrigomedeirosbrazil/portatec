<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccessCode;
use App\Models\AccessEvent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Spec §8. O admin do dispositivo vê tudo. O membro de local vê o que veio
     * de um local dele — mais os eventos ambíguos que têm um candidato dele.
     * Com 16 unidades no mesmo portão de pedestres, a regra antiga fazia cada
     * morador acompanhar a movimentação de todos os outros.
     */
    public function view(User $user, AccessEvent $accessEvent): bool
    {
        $device = $accessEvent->device;

        if ($device === null) {
            return false;
        }

        if ($device->isAdministeredBy($user)) {
            return true;
        }

        $userPlaceIds = $user->placeUsers()->pluck('place_id');

        if ($accessEvent->access_code_id !== null) {
            return $accessEvent->accessCode !== null
                && $userPlaceIds->contains($accessEvent->accessCode->place_id);
        }

        $candidateIds = (array) data_get($accessEvent->metadata, 'candidate_access_code_ids', []);

        if ($candidateIds === []) {
            return false;
        }

        return AccessCode::query()
            ->whereKey($candidateIds)
            ->whereIn('place_id', $userPlaceIds)
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function delete(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function restore(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function forceDelete(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, AccessEvent $accessEvent): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
