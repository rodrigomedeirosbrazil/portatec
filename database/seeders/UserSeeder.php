<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $adminId = DB::table('users')->insertGetId([
            'name' => 'Portatec Admin',
            'email' => 'contato@medeirostec.com.br',
            'password' => Hash::make('123'),
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $hostId = DB::table('users')->insertGetId([
            'name' => 'Host Demo',
            'email' => 'host@portatec.test',
            'password' => Hash::make('123'),
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'Beach House',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('place_users')->insert([
            [
                'place_id' => $placeId,
                'user_id' => $adminId,
                'role' => 'admin',
                'label' => 'Administrador',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'place_id' => $placeId,
                'user_id' => $hostId,
                'role' => 'host',
                'label' => 'Host',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $deviceId = DB::table('devices')->insertGetId([
            'place_id' => $placeId,
            'name' => 'Portao Garagem',
            'brand' => 'portatec',
            'external_device_id' => '37feb9',
            'default_pin' => '123456',
            'last_sync' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $buttonFunctionId = DB::table('device_functions')->insertGetId([
            'device_id' => $deviceId,
            'type' => 'button',
            'pin' => '3',
            'status' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sensorFunctionId = DB::table('device_functions')->insertGetId([
            'device_id' => $deviceId,
            'type' => 'sensor',
            'pin' => '0',
            'status' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('place_device_functions')->insert([
            [
                'place_id' => $placeId,
                'device_function_id' => $buttonFunctionId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'place_id' => $placeId,
                'device_function_id' => $sensorFunctionId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
