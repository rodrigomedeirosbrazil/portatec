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
    }
};
