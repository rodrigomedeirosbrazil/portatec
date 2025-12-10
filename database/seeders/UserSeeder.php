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
        // Gera as permissões do Filament Shield para ambos os painéis
        // Usa --panel para evitar prompt interativo e --option para evitar confirm
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'app',
            '--option' => 'policies_and_permissions',
        ]);

        // Gera também para o painel admin (onde está o UserResource)
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
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

        // Atribui permissões ao role host (acesso aos recursos do painel app)
        // Host pode visualizar places, devices, command logs e access pins
        // Host pode criar, editar e excluir access pins
        $hostPermissions = Permission::where('guard_name', 'web')
            ->where(function ($query) {
                // Permissões de visualização para places
                $query->where('name', 'view_any_place')
                    ->orWhere('name', 'view_place')
                    // Permissões de visualização para devices
                    ->orWhere('name', 'view_any_device')
                    ->orWhere('name', 'view_device')
                    // Permissões de visualização para command logs
                    ->orWhere('name', 'view_any_command::log')
                    ->orWhere('name', 'view_command::log')
                    // Permissões para access pins (visualizar, criar, editar, excluir)
                    ->orWhere('name', 'view_any_access::pin')
                    ->orWhere('name', 'view_access::pin')
                    ->orWhere('name', 'create_access::pin')
                    ->orWhere('name', 'update_access::pin')
                    ->orWhere('name', 'delete_access::pin');
            })
            ->get();

        $hostRole->syncPermissions($hostPermissions);

        // Cria/atualiza o role panel_user (já é criado pelo Shield, mas garantimos que existe)
        $panelUserRole = Role::firstOrCreate([
            'name' => 'panel_user',
            'guard_name' => 'web',
        ]);

        // Panel User não precisa de permissões explícitas, apenas acesso básico ao painel
        // As permissões são gerenciadas pelo Filament Shield automaticamente
        // Garantimos que não tem permissões atribuídas (apenas acesso ao painel)
        $panelUserRole->syncPermissions([]);

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
            'name' => 'Teste',
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
            'name' => 'Dispositivo de teste',
            'chip_id' => '37feb9',
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
