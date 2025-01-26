<?php

namespace Database\Seeders;

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
            'name' => 'super-admin',
            'guard_name' => 'web'
        ]);

        Role::create([
            'name' => 'owner',
            'guard_name' => 'web'
        ]);

        Role::create([
            'name' => 'host',
            'guard_name' => 'web'
        ]);

        // Create super-admin user
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'rodrigo@medeirostec.com.br',
            'password' => Hash::make('123qweasd'),
            'email_verified_at' => now(),
        ]);

        // Assign role to user
        $superAdmin->assignRole($superAdminRole);
    }
}
