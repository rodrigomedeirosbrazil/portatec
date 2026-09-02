<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Enums\PlaceRoleEnum;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DeviceCurrentPlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_uses_the_current_place_from_the_session(): void
    {
        $user = User::factory()->create();
        $mine = Place::create(['name' => 'Casa Azul']);
        $other = Place::create(['name' => 'Casa Verde']);

        foreach ([$mine, $other] as $place) {
            PlaceUser::create([
                'place_id' => $place->id,
                'user_id' => $user->id,
                'role' => PlaceRoleEnum::Admin,
                'label' => $user->name,
            ]);
        }

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $mine->id]);

        $this->actingAs($user)
            ->get('/app/devices')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('devices/index')
                ->where('filters.place_id', (string) $mine->id)
            );
    }

    public function test_place_id_unassigned_filters_devices_without_a_place(): void
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa Azul']);

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => PlaceRoleEnum::Admin,
            'label' => $user->name,
        ]);

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $place->id]);

        $this->actingAs($user)
            ->get('/app/devices?place_id=unassigned')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('devices/index')
                ->where('filters.place_id', 'unassigned')
            );
    }
}
