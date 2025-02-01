<?php

namespace Database\Seeders;

use App\Enums\DeviceTypeEnum;
use App\Enums\PlaceRoleEnum;
use App\Enums\PropertyRoleEnum;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\Property;
use App\Models\PropertyUser;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            'type' => DeviceTypeEnum::Button,
            'topic' => 'stat/esmeralda6/POWER1',
            'command_topic' => 'cmnd/esmeralda6/POWER1',
            'payload_on' => 'ON',
            'payload_off' => 'OFF',
        ]);

        $portaoGaragem->placeDevices()->create([
            'place_id' => $place->id,
        ]);

        $statusPortaoGaragem = Device::create([
            'name' => 'Status portão garagem',
            'type' => DeviceTypeEnum::Sensor,
            'topic' => 'stat/esmeralda6/RESULT',
            'json_attribute' => 'POWER2',
            'payload_on' => 'ON',
            'payload_off' => 'OFF',
        ]);

        $statusPortaoGaragem->placeDevices()->create([
            'place_id' => $place->id,
        ]);
    }
}

