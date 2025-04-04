<?php

namespace Database\Seeders;

use App\Enums\DeviceRelatedTypeEnum;
use App\Enums\DeviceTypeEnum;
use App\Enums\PlaceRoleEnum;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\Tuya;
use App\Models\TuyaDevice;
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

        $portaoPedestres = Device::create([
            'name' => 'Portão pedestres',
            'type' => DeviceTypeEnum::Button,
            'device_type' => DeviceRelatedTypeEnum::Tuya,
        ]);

        $tuya = Tuya::create([
            'device_id' => $portaoPedestres->id,
            'client_id' => '1234567890',
            'client_secret' => '1234567890',
            'uid' => '1234567890',
        ]);

        TuyaDevice::create([
            'tuya_id' => $tuya->id,
            'device_id' => '1234567890',
            'local_key' => '1234567890',
        ]);
    }
}

