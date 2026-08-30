<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlaceRoleEnum;
use App\Models\Place;
use App\Models\PlaceUser;

class PlaceMemberService
{
    public function create(Place $place, int $userId, string $role, ?string $label): PlaceUser
    {
        return PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $userId,
            'role' => $role,
            'label' => $label,
        ]);
    }

    /**
     * Removes a member from a place, unless it is the place's last admin.
     *
     * @return bool `true` when removed, `false` when blocked (last admin).
     */
    public function remove(Place $place, PlaceUser $placeUser): bool
    {
        if ($placeUser->role === PlaceRoleEnum::Admin->value) {
            $adminCount = $place->placeUsers()
                ->where('role', PlaceRoleEnum::Admin->value)
                ->count();

            if ($adminCount <= 1) {
                return false;
            }
        }

        $placeUser->delete();

        return true;
    }
}
