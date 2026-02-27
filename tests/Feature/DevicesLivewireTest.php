<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DevicesLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_owned_place_devices(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $placeA = DB::table('places')->insertGetId(['name' => 'Casa A', 'created_at' => now(), 'updated_at' => now()]);
        $placeB = DB::table('places')->insertGetId(['name' => 'Casa B', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('place_users')->insert([
            'place_id' => $placeA,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('place_users')->insert([
            'place_id' => $placeB,
            'user_id' => $otherUser->id,
            'role' => 'admin',
            'label' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('devices')->insert([
            [
                'place_id' => $placeA,
                'name' => 'Portao Casa A',
                'brand' => 'portatec',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'place_id' => $placeB,
                'name' => 'Portao Casa B',
                'brand' => 'portatec',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get('/app/devices');

        $response->assertOk();
        $response->assertSee('Portao Casa A');
        $response->assertDontSee('Portao Casa B');
    }

    public function test_user_cannot_open_device_show_from_other_place(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $placeA = DB::table('places')->insertGetId(['name' => 'Casa A', 'created_at' => now(), 'updated_at' => now()]);
        $placeB = DB::table('places')->insertGetId(['name' => 'Casa B', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('place_users')->insert([
            [
                'place_id' => $placeA,
                'user_id' => $user->id,
                'role' => 'admin',
                'label' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'place_id' => $placeB,
                'user_id' => $otherUser->id,
                'role' => 'admin',
                'label' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $foreignDeviceId = DB::table('devices')->insertGetId([
            'place_id' => $placeB,
            'name' => 'Portao Casa B',
            'brand' => 'portatec',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/app/devices/{$foreignDeviceId}")
            ->assertForbidden();

        $this->actingAs($user)
            ->get("/app/devices/{$foreignDeviceId}/control")
            ->assertForbidden();
    }
}
