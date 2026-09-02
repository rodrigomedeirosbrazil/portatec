<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DeviceStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlace(): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa Azul']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return [$user, $place];
    }

    public function test_status_offline_returns_only_unavailable_devices(): void
    {
        [$user, $place] = $this->userWithPlace();

        $online = Device::factory()->create([
            'place_id' => $place->id,
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subMinute(),
        ]);
        $offline = Device::factory()->create([
            'place_id' => $place->id,
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get('/app/devices?status=offline')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('devices/index')
                ->where('filters.status', 'offline')
                ->has('devices.data', 1)
                ->where('devices.data.0.id', $offline->id)
            );

        $this->actingAs($user)
            ->get('/app/devices?status=online')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('devices.data', 1)
                ->where('devices.data.0.id', $online->id)
            );
    }

    public function test_without_status_all_devices_are_returned(): void
    {
        [$user, $place] = $this->userWithPlace();

        Device::factory()->count(2)->create([
            'place_id' => $place->id,
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get('/app/devices')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('devices.data', 2));
    }

    /**
     * O filtro não pode furar o escopo por place: um status na query string não
     * dá acesso a dispositivo de outra conta.
     */
    public function test_status_filter_does_not_leak_devices_from_other_users(): void
    {
        [$user] = $this->userWithPlace();

        $otherPlace = Place::create(['name' => 'De outro']);
        Device::factory()->create([
            'place_id' => $otherPlace->id,
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get('/app/devices?status=offline')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('devices.data', 0));
    }
}
