<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceBrandEnum;
use App\Enums\PlaceRoleEnum;
use App\Models\Booking;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makePlaceWithAdmin(User $user, string $name = 'Casa da Praia'): Place
    {
        $place = Place::create(['name' => $name]);

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => PlaceRoleEnum::Admin,
            'label' => $user->name,
        ]);

        return $place;
    }

    public function test_index_renders_dashboard(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('dashboard'));
    }

    public function test_aggregates_are_scoped_to_the_users_places(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $online = Device::create([
            'name' => 'Fechadura Online',
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now(),
        ]);
        $online->places()->attach($place->id);

        $offline = Device::create([
            'name' => 'Fechadura Offline',
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subHour(),
        ]);
        $offline->places()->attach($place->id);

        Booking::create([
            'place_id' => $place->id,
            'guest_name' => 'Ativo',
            'check_in' => now()->subHour(),
            'check_out' => now()->addHour(),
            'source' => 'manual',
        ]);
        Booking::create([
            'place_id' => $place->id,
            'guest_name' => 'Check-in Hoje',
            'check_in' => now()->addHours(2),
            'check_out' => now()->addHours(10),
            'source' => 'manual',
        ]);

        // Outro usuario, cujos dados NAO devem entrar na conta.
        $otherUser = User::factory()->create();
        $otherPlace = $this->makePlaceWithAdmin($otherUser, 'Casa de Outro Usuario');

        $foreignDevice = Device::create([
            'name' => 'Dispositivo Alheio',
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now(),
        ]);
        $foreignDevice->places()->attach($otherPlace->id);
        Booking::create([
            'place_id' => $otherPlace->id,
            'guest_name' => 'Hospede Alheio',
            'check_in' => now()->subHour(),
            'check_out' => now()->addHour(),
            'source' => 'manual',
        ]);

        $response = $this->actingAs($user)->get('/app/dashboard');

        $response->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertSame(2, $props['totalDevices']);
            $this->assertSame(1, $props['totalOnline']);
            $this->assertSame(1, $props['totalOffline']);
            $this->assertSame(1, $props['activeBookings']);
            $this->assertSame(1, $props['todayCheckIns']);
            $this->assertCount(1, $props['places']);
        });
    }
}
