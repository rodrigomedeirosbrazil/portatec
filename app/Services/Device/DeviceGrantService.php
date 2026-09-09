<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\Enums\DeviceRoleEnum;
use App\Models\Device;
use App\Models\User;
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

    public function revoke(Device $device, User $user): void
    {
        $device->deviceUsers()
            ->where('user_id', $user->id)
            ->where('role', DeviceRoleEnum::User)
            ->delete();
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
