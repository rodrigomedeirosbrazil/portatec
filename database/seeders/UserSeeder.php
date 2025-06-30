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
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminRole = Role::create([
            'name' => 'super_admin',
            'guard_name' => 'web'
        ]);

        User::create([
            'name' => 'Super Admin',
            'email' => 'contato@medeirostec.com.br',
            'password' => Hash::make('123'),
            'email_verified_at' => now(),
        ])
            ->assignRole($superAdminRole);

        $hostRole = Role::create([
            'name' => 'host',
            'guard_name' => 'web'
        ]);

        $rodrigo = User::create([
            'name' => 'Rodrigo',
            'email' => 'rodrigo@medeirostec.com.br',
            'password' => Hash::make('123'),
            'email_verified_at' => now(),
        ])
            ->assignRole($hostRole);

        $maitte = User::create([
            'name' => 'Maittê',
            'email' => 'maitte.andrade@gmail.com',
            'password' => Hash::make('123'),
            'email_verified_at' => now(),
        ])
            ->assignRole($hostRole);

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

