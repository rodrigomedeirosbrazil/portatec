<?php

declare(strict_types=1);

use App\Enums\DeviceRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_user', function (Blueprint $table): void {
            $table->enum('role', DeviceRoleEnum::values())
                ->default(DeviceRoleEnum::User->value)
                ->after('user_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('device_user', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    /**
     * Regra do spec §4: (1) quem já está vinculado, o mais antigo vira admin;
     * (2) senão, o admin mais antigo do local primário; (3) senão, sem admin.
     * Dispositivo sem admin é estado inerte — ninguém edita, concede ou anexa.
     */
    private function backfill(): void
    {
        $oldestPerDevice = DB::table('device_user')
            ->selectRaw('MIN(id) as id')
            ->groupBy('device_id')
            ->pluck('id');

        DB::table('device_user')
            ->whereIn('id', $oldestPerDevice)
            ->update(['role' => DeviceRoleEnum::Admin->value]);

        $devicesWithoutLink = DB::table('devices')
            ->whereNotExists(function ($query): void {
                $query->selectRaw(1)
                    ->from('device_user')
                    ->whereColumn('device_user.device_id', 'devices.id');
            })
            ->get(['id', 'place_id']);

        foreach ($devicesWithoutLink as $device) {
            $placeId = $device->place_id ?? DB::table('device_place')
                ->where('device_id', $device->id)
                ->orderBy('id')
                ->value('place_id');

            if ($placeId === null) {
                continue;
            }

            $userId = DB::table('place_users')
                ->where('place_id', $placeId)
                ->where('role', 'admin')
                ->orderBy('id')
                ->value('user_id');

            if ($userId === null) {
                continue;
            }

            DB::table('device_user')->insert([
                'device_id' => $device->id,
                'user_id' => $userId,
                'role' => DeviceRoleEnum::Admin->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->grantToRemainingPlaceAdmins();
    }

    /**
     * Um local pode ter mais de um admin, e antes desta mudança todos eles
     * configuravam e reanexavam qualquer dispositivo do local. O backfill
     * escolhe UM deles como admin do dispositivo — os outros ficariam sem
     * vínculo nenhum, e aí caem numa porta de mão única: `PlacePolicy::update`
     * ainda os deixa DESANEXAR o dispositivo do local, mas `DevicePolicy::attach`
     * não os deixa ANEXAR de volta. Local quebrado sem quem consiga arrumar.
     *
     * Verificado num dump de produção: o local "Village" tem dois admins, e o
     * segundo ficava exatamente nesse estado.
     *
     * A concessão de uso resolve sem alargar nada: quem já mandava no local
     * continua podendo usar e reanexar, e só o admin do dispositivo configura.
     * Membro `host` não entra aqui — ele nunca pôde desanexar, então não tem
     * porta de mão única para fechar, e um vínculo lhe daria acesso novo.
     */
    private function grantToRemainingPlaceAdmins(): void
    {
        $admins = DB::table('device_user')
            ->where('role', DeviceRoleEnum::Admin->value)
            ->get(['device_id', 'user_id']);

        foreach ($admins as $admin) {
            $placeIds = DB::table('device_place')
                ->where('device_id', $admin->device_id)
                ->pluck('place_id')
                ->push(
                    DB::table('devices')->where('id', $admin->device_id)->value('place_id')
                )
                ->filter()
                ->unique();

            if ($placeIds->isEmpty()) {
                continue;
            }

            $otherAdminIds = DB::table('place_users')
                ->whereIn('place_id', $placeIds)
                ->where('role', 'admin')
                ->where('user_id', '!=', $admin->user_id)
                ->pluck('user_id')
                ->unique();

            foreach ($otherAdminIds as $userId) {
                $alreadyLinked = DB::table('device_user')
                    ->where('device_id', $admin->device_id)
                    ->where('user_id', $userId)
                    ->exists();

                if ($alreadyLinked) {
                    continue;
                }

                DB::table('device_user')->insert([
                    'device_id' => $admin->device_id,
                    'user_id' => $userId,
                    'role' => DeviceRoleEnum::User->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
