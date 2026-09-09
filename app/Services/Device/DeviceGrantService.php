<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\Enums\DeviceRoleEnum;
use App\Enums\PlaceRoleEnum;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use App\Services\AccessCodeSyncService;
use Illuminate\Support\Facades\DB;

/**
 * Spec §6. Conceder, revogar e transferir mexem só em `device_user`. A cascata
 * da revogação (desanexar dos locais e apagar os PINs do equipamento) mora na
 * Task 3.1 e é plugada aqui.
 */
class DeviceGrantService
{
    public function grant(Device $device, User $user): void
    {
        $device->deviceUsers()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => DeviceRoleEnum::User->value],
        );
    }

    /**
     * Spec §6: corta na hora, sem aviso. Hóspede com estadia em andamento
     * perde o acesso a este dispositivo — foi escolha deliberada, porque o
     * síndico precisa conseguir cortar justamente no momento do conflito.
     */
    public function revoke(Device $device, User $user): void
    {
        DB::transaction(function () use ($device, $user): void {
            $device->deviceUsers()
                ->where('user_id', $user->id)
                ->where('role', DeviceRoleEnum::User)
                ->delete();

            foreach ($this->placesLosingAccess($device, $user) as $placeId) {
                $this->detachAndPurge($device, $placeId);
            }
        });
    }

    /**
     * Locais onde este usuário é admin, o dispositivo está anexado, e nenhum
     * outro admin do local sustenta o acesso.
     *
     * @return array<int, int>
     */
    private function placesLosingAccess(Device $device, User $user): array
    {
        $attachedPlaceIds = $device->places()->pluck('places.id');

        return $user->placeUsers()
            ->where('role', PlaceRoleEnum::Admin)
            ->whereIn('place_id', $attachedPlaceIds)
            ->pluck('place_id')
            ->reject(function (int $placeId) use ($device, $user): bool {
                return PlaceUser::query()
                    ->where('place_id', $placeId)
                    ->where('role', PlaceRoleEnum::Admin)
                    ->where('user_id', '!=', $user->id)
                    ->whereIn('user_id', $device->deviceUsers()->pluck('user_id'))
                    ->exists();
            })
            ->values()
            ->all();
    }

    private function detachAndPurge(Device $device, int $placeId): void
    {
        $place = Place::query()->find($placeId);

        if ($place === null) {
            return;
        }

        foreach ($place->getValidAccessCodes() as $accessCode) {
            app(AccessCodeSyncService::class)->syncDeletedAccessCode($accessCode);
        }

        $device->places()->detach($placeId);

        $device->load('places');
        $device->update(['place_id' => $device->places->first()?->id]);
    }

    /**
     * Troca os papéis: quem recebe vira admin, quem transferiu vira concessão.
     * Mantém o uso e perde a configuração — spec §6.
     */
    public function transfer(Device $device, User $newAdmin): void
    {
        DB::transaction(function () use ($device, $newAdmin): void {
            $device->deviceUsers()
                ->where('role', DeviceRoleEnum::Admin)
                ->update(['role' => DeviceRoleEnum::User->value]);

            $device->deviceUsers()->updateOrCreate(
                ['user_id' => $newAdmin->id],
                ['role' => DeviceRoleEnum::Admin->value],
            );
        });
    }
}
