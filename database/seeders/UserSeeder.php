<?php

namespace Database\Seeders;

use App\Enums\DeviceTypeEnum;
use App\Enums\PlaceRoleEnum;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Models\DeviceUser;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Gera as permissões do Filament Shield
        // Usa --panel para evitar prompt interativo e --option para evitar confirm
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'app',
            '--option' => 'policies_and_permissions',
        ]);

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        // Atribui todas as permissões ao super_admin
        $permissions = Permission::where('guard_name', 'web')->get();
        $superAdminRole->syncPermissions($permissions);

        User::firstOrCreate(
            ['email' => 'contato@medeirostec.com.br'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('123'),
                'email_verified_at' => now(),
            ]
        )->assignRole($superAdminRole);

        $hostRole = Role::firstOrCreate([
            'name' => 'host',
            'guard_name' => 'web',
        ]);

        $rodrigo = User::firstOrCreate(
            ['email' => 'rodrigo@medeirostec.com.br'],
            [
                'name' => 'Rodrigo',
                'password' => Hash::make('123'),
                'email_verified_at' => now(),
            ]
        );

        if (!$rodrigo->hasRole($hostRole)) {
            $rodrigo->assignRole($hostRole);
        }

        $maitte = User::firstOrCreate(
            ['email' => 'maitte.andrade@gmail.com'],
            [
                'name' => 'Maittê',
                'password' => Hash::make('123'),
                'email_verified_at' => now(),
            ]
        );

        if (!$maitte->hasRole($hostRole)) {
            $maitte->assignRole($hostRole);
        }

        $place = Place::create([
            'name' => 'Beach House',
        ]);

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $rodrigo->id,
            'role' => PlaceRoleEnum::Admin,
        ]);

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $maitte->id,
            'role' => PlaceRoleEnum::Host,
        ]);

        $portaoGaragem = Device::create([
            'name' => 'Portão garagem',
            'chip_id' => '123123',
        ]);

        $portaoGaragemPulse = DeviceFunction::create([
            'device_id' => $portaoGaragem->id,
            'type' => DeviceTypeEnum::Button,
            'pin' => 3,
        ]);

        $portaoGaragemSensor = DeviceFunction::create([
            'device_id' => $portaoGaragem->id,
            'type' => DeviceTypeEnum::Sensor,
            'pin' => 0,
        ]);

        $place->placeDeviceFunctions()->create([
            'device_function_id' => $portaoGaragemPulse->id,
        ]);

        $place->placeDeviceFunctions()->create([
            'device_function_id' => $portaoGaragemSensor->id,
        ]);

        DeviceUser::create([
            'device_id' => $portaoGaragem->id,
            'user_id' => $rodrigo->id,
        ]);
    }
}
