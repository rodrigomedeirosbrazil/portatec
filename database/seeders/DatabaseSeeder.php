<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);

        Platform::firstOrCreate(
            ['slug' => 'tuya'],
            ['name' => 'Tuya SmartLife'],
        );
    }
}
