<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Enums\DeviceBrandEnum;
use App\Enums\DeviceTypeEnum;
use App\Enums\PlaceRoleEnum;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicesTest extends TestCase
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

    // ------------------------------------------------------------------
    // GET screens render the right Inertia component
    // ------------------------------------------------------------------

    public function test_index_renders_devices_index(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/devices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('devices/index'));
    }

    public function test_create_renders_devices_create(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/devices/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('devices/create'));
    }

    public function test_show_renders_devices_show(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $device = Device::create(['name' => 'Fechadura', 'brand' => DeviceBrandEnum::Portatec]);
        $device->places()->attach($place->id);

        $this->actingAs($user)
            ->get("/app/devices/{$device->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('devices/show'));
    }

    public function test_edit_renders_devices_edit(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $device = Device::create(['name' => 'Fechadura', 'brand' => DeviceBrandEnum::Portatec]);
        $device->places()->attach($place->id);

        $this->actingAs($user)
            ->get("/app/devices/{$device->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('devices/edit'));
    }

    public function test_control_renders_devices_control(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $device = Device::create(['name' => 'Fechadura', 'brand' => DeviceBrandEnum::Portatec]);
        $device->places()->attach($place->id);

        $this->actingAs($user)
            ->get("/app/devices/{$device->id}/control")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('devices/control'));
    }

    public function test_integrations_index_renders_devices_integrations_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/devices/integrations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('devices/integrations/index'));
    }

    public function test_tuya_connect_renders_devices_integrations_tuya_connect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/devices/integrations/tuya-connect')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('devices/integrations/tuya-connect'));
    }

    // ------------------------------------------------------------------
    // Scoping: a user cannot see or reach devices from another account
    // ------------------------------------------------------------------

    public function test_it_does_not_list_a_device_from_another_account(): void
    {
        $stranger = User::factory()->create();
        $strangerPlace = $this->makePlaceWithAdmin($stranger, 'Casa do estranho');
        $foreignDevice = Device::create(['name' => 'Portao alheio', 'brand' => DeviceBrandEnum::Portatec]);
        $foreignDevice->places()->attach($strangerPlace->id);

        $owner = User::factory()->create();
        $this->makePlaceWithAdmin($owner, 'Casa do dono');

        $this->actingAs($owner)
            ->get('/app/devices')
            ->assertOk()
            ->assertDontSee('Portao alheio');
    }

    public function test_user_cannot_show_a_device_from_another_account(): void
    {
        $stranger = User::factory()->create();
        $strangerPlace = $this->makePlaceWithAdmin($stranger, 'Casa do estranho');
        $foreignDevice = Device::create(['name' => 'Portao alheio', 'brand' => DeviceBrandEnum::Portatec]);
        $foreignDevice->places()->attach($strangerPlace->id);

        $owner = User::factory()->create();
        $this->makePlaceWithAdmin($owner, 'Casa do dono');

        $this->actingAs($owner)
            ->get("/app/devices/{$foreignDevice->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_edit_a_device_from_another_account(): void
    {
        $stranger = User::factory()->create();
        $strangerPlace = $this->makePlaceWithAdmin($stranger, 'Casa do estranho');
        $foreignDevice = Device::create(['name' => 'Portao alheio', 'brand' => DeviceBrandEnum::Portatec]);
        $foreignDevice->places()->attach($strangerPlace->id);

        $owner = User::factory()->create();
        $this->makePlaceWithAdmin($owner, 'Casa do dono');

        $this->actingAs($owner)
            ->get("/app/devices/{$foreignDevice->id}/edit")
            ->assertForbidden();
    }

    public function test_user_cannot_update_a_device_from_another_account(): void
    {
        $stranger = User::factory()->create();
        $strangerPlace = $this->makePlaceWithAdmin($stranger, 'Casa do estranho');
        $foreignDevice = Device::create(['name' => 'Portao alheio', 'brand' => DeviceBrandEnum::Portatec]);
        $foreignDevice->places()->attach($strangerPlace->id);

        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner, 'Casa do dono');

        $this->actingAs($owner)
            ->put("/app/devices/{$foreignDevice->id}", [
                'placeIds' => [$ownerPlace->id],
                'name' => 'Hackeado',
                'brand' => 'portatec',
                'deviceFunctions' => [
                    ['id' => null, 'type' => 'switch', 'pin' => '1'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_control_a_device_from_another_account(): void
    {
        $stranger = User::factory()->create();
        $strangerPlace = $this->makePlaceWithAdmin($stranger, 'Casa do estranho');
        $foreignDevice = Device::create(['name' => 'Portao alheio', 'brand' => DeviceBrandEnum::Portatec]);
        $foreignDevice->places()->attach($strangerPlace->id);

        $owner = User::factory()->create();
        $this->makePlaceWithAdmin($owner, 'Casa do dono');

        $this->actingAs($owner)
            ->get("/app/devices/{$foreignDevice->id}/control")
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Filters: place_id, unassigned, search
    // ------------------------------------------------------------------

    public function test_filtering_by_place_id_shows_only_that_places_devices(): void
    {
        $user = User::factory()->create();
        $placeA = $this->makePlaceWithAdmin($user, 'Casa A');
        $placeB = $this->makePlaceWithAdmin($user, 'Casa B');

        $deviceA = Device::create(['name' => 'Dispositivo A', 'brand' => DeviceBrandEnum::Portatec]);
        $deviceA->places()->attach($placeA->id);

        $deviceB = Device::create(['name' => 'Dispositivo B', 'brand' => DeviceBrandEnum::Portatec]);
        $deviceB->places()->attach($placeB->id);

        $this->actingAs($user)
            ->get("/app/devices?place_id={$placeA->id}")
            ->assertOk()
            ->assertSee('Dispositivo A')
            ->assertDontSee('Dispositivo B');
    }

    public function test_filtering_by_unassigned_shows_only_devices_without_a_place(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $orphan = Device::create(['name' => 'Sem local', 'brand' => DeviceBrandEnum::Portatec]);
        $orphan->deviceUsers()->create(['user_id' => $user->id]);

        $attached = Device::create(['name' => 'Com local', 'brand' => DeviceBrandEnum::Portatec]);
        $attached->places()->attach($place->id);

        $this->actingAs($user)
            ->get('/app/devices?place_id=unassigned')
            ->assertOk()
            ->assertSee('Sem local')
            ->assertDontSee('Com local');
    }

    public function test_search_filters_devices_by_name(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $match = Device::create(['name' => 'Fechadura Frontal', 'brand' => DeviceBrandEnum::Portatec]);
        $match->places()->attach($place->id);

        $noMatch = Device::create(['name' => 'Portao Garagem', 'brand' => DeviceBrandEnum::Portatec]);
        $noMatch->places()->attach($place->id);

        $this->actingAs($user)
            ->get('/app/devices?search=Frontal')
            ->assertOk()
            ->assertSee('Fechadura Frontal')
            ->assertDontSee('Portao Garagem');
    }

    // ------------------------------------------------------------------
    // Create: reject placeIds the user doesn't own
    // ------------------------------------------------------------------

    public function test_store_rejects_place_ids_the_user_does_not_own(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user, 'Casa do usuario');

        $stranger = User::factory()->create();
        $strangerPlace = $this->makePlaceWithAdmin($stranger, 'Casa do estranho');

        $this->actingAs($user)
            ->post('/app/devices', [
                'placeIds' => [$strangerPlace->id],
                'name' => 'Dispositivo forjado',
                'brand' => 'portatec',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('devices', ['name' => 'Dispositivo forjado']);
    }

    public function test_store_creates_device_with_owned_place_ids(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->post('/app/devices', [
                'placeIds' => [$place->id],
                'name' => 'Novo dispositivo',
                'brand' => 'portatec',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('devices', ['name' => 'Novo dispositivo', 'place_id' => $place->id]);
    }

    // ------------------------------------------------------------------
    // Edit: reconcile device functions + sync place_device_functions
    // ------------------------------------------------------------------

    public function test_update_deletes_removed_function_updates_existing_and_creates_new_and_resyncs_places(): void
    {
        $user = User::factory()->create();
        $placeA = $this->makePlaceWithAdmin($user, 'Casa A');
        $placeB = $this->makePlaceWithAdmin($user, 'Casa B');

        $device = Device::create(['name' => 'Dispositivo', 'brand' => DeviceBrandEnum::Portatec]);
        $device->places()->attach($placeA->id);

        $toKeep = DeviceFunction::create(['device_id' => $device->id, 'type' => DeviceTypeEnum::Switch, 'pin' => '1']);
        $toRemove = DeviceFunction::create(['device_id' => $device->id, 'type' => DeviceTypeEnum::Button, 'pin' => '2']);

        PlaceDeviceFunction::create(['place_id' => $placeA->id, 'device_function_id' => $toKeep->id]);
        PlaceDeviceFunction::create(['place_id' => $placeA->id, 'device_function_id' => $toRemove->id]);

        $this->actingAs($user)
            ->put("/app/devices/{$device->id}", [
                'placeIds' => [$placeB->id],
                'name' => 'Dispositivo atualizado',
                'brand' => 'portatec',
                'deviceFunctions' => [
                    ['id' => $toKeep->id, 'type' => 'sensor', 'pin' => '9'],
                    ['id' => null, 'type' => 'button', 'pin' => '3'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => 'Dispositivo atualizado']);

        // updated existing function
        $this->assertDatabaseHas('device_functions', ['id' => $toKeep->id, 'type' => 'sensor', 'pin' => '9']);

        // removed function no longer exists
        $this->assertDatabaseMissing('device_functions', ['id' => $toRemove->id]);

        // new function created
        $this->assertDatabaseHas('device_functions', ['device_id' => $device->id, 'type' => 'button', 'pin' => '3']);

        // places pivot resynced to placeB only
        $this->assertTrue($device->fresh()->places()->where('places.id', $placeB->id)->exists());
        $this->assertFalse($device->fresh()->places()->where('places.id', $placeA->id)->exists());

        // place_device_functions resynced: old place link for kept function gone, no leftover for removed function
        $this->assertDatabaseMissing('place_device_functions', ['place_id' => $placeA->id, 'device_function_id' => $toKeep->id]);
        $this->assertDatabaseMissing('place_device_functions', ['device_function_id' => $toRemove->id]);
    }

    public function test_update_rejects_place_ids_the_user_does_not_own(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $device = Device::create(['name' => 'Dispositivo', 'brand' => DeviceBrandEnum::Portatec]);
        $device->places()->attach($place->id);

        $stranger = User::factory()->create();
        $strangerPlace = $this->makePlaceWithAdmin($stranger, 'Casa do estranho');

        $this->actingAs($user)
            ->put("/app/devices/{$device->id}", [
                'placeIds' => [$strangerPlace->id],
                'name' => 'Dispositivo',
                'brand' => 'portatec',
                'deviceFunctions' => [
                    ['id' => null, 'type' => 'switch', 'pin' => '1'],
                ],
            ])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Commands: reject non-numeric pin
    // ------------------------------------------------------------------

    public function test_send_command_by_device_rejects_non_numeric_pin(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $device = Device::create(['name' => 'Dispositivo', 'brand' => DeviceBrandEnum::Portatec, 'external_device_id' => 'chip-1']);
        $device->places()->attach($place->id);

        $this->actingAs($user)
            ->post("/app/devices/{$device->id}/commands", [
                'action' => 'toggle',
                'pin' => 'abc',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => __('app.invalid_command_pin')]);
    }

    public function test_send_command_by_device_denies_access_from_another_account(): void
    {
        $stranger = User::factory()->create();
        $strangerPlace = $this->makePlaceWithAdmin($stranger, 'Casa do estranho');
        $device = Device::create(['name' => 'Dispositivo', 'brand' => DeviceBrandEnum::Portatec, 'external_device_id' => 'chip-1']);
        $device->places()->attach($strangerPlace->id);

        $owner = User::factory()->create();
        $this->makePlaceWithAdmin($owner, 'Casa do dono');

        $this->actingAs($owner)
            ->post("/app/devices/{$device->id}/commands", [
                'action' => 'toggle',
                'pin' => '1',
            ])
            ->assertForbidden();
    }
}
