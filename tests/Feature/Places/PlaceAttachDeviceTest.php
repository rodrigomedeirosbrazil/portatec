<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceAttachDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_cannot_attach_a_device_from_another_account(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();

        $gate = $this->deviceOwnedBy($victim, 'Portão da vítima');
        $place = $this->placeAdministeredBy($attacker);

        $this->actingAs($attacker)
            ->post("/app/places/{$place->id}/devices/attach", ['deviceId' => $gate->id])
            ->assertForbidden();

        $this->assertSame(0, $gate->places()->count());
    }

    public function test_attach_list_does_not_leak_other_accounts_devices(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();

        $this->deviceOwnedBy($victim, 'Portão da vítima');
        $place = $this->placeAdministeredBy($attacker);

        $this->actingAs($attacker)
            ->get("/app/places/{$place->id}/devices/attach")
            ->assertOk()
            ->assertDontSee('Portão da vítima');
    }

    public function test_grantee_can_attach_to_own_place(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner, 'Garagem A');
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $place = $this->placeAdministeredBy($resident, 'Apto 1');

        $this->actingAs($resident)
            ->post("/app/places/{$place->id}/devices/attach", ['deviceId' => $gate->id])
            ->assertRedirect("/app/places/{$place->id}");

        $this->assertTrue($gate->places()->where('places.id', $place->id)->exists());
    }

    public function test_host_cannot_attach(): void
    {
        $owner = User::factory()->create();
        $host = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner, 'Garagem A');
        $gate->deviceUsers()->create(['user_id' => $host->id, 'role' => 'user']);

        $place = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $owner->id, 'role' => 'admin']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $host->id, 'role' => 'host']);

        $this->actingAs($host)
            ->post("/app/places/{$place->id}/devices/attach", ['deviceId' => $gate->id])
            ->assertForbidden();
    }

    public function test_editing_a_shared_device_does_not_change_other_places(): void
    {
        $owner = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner, 'Garagem A');
        $condo = $this->placeAdministeredBy($owner, 'Condomínio');
        $apto = Place::create(['name' => 'Apto 2']);

        $gate->places()->attach([$condo->id, $apto->id]);

        $this->actingAs($owner)
            ->put("/app/devices/{$gate->id}", [
                'name' => 'Garagem A renomeada',
                'brand' => 'portatec',
                'external_device_id' => 'chip-a',
                'placeIds' => [$condo->id],
                'deviceFunctions' => [['id' => null, 'type' => 'switch', 'pin' => '2']],
            ])
            ->assertRedirect("/app/devices/{$gate->id}");

        $this->assertTrue(
            $gate->places()->where('places.id', $apto->id)->exists(),
            'Editar o dispositivo não pode desanexá-lo dos locais dos outros.'
        );
    }

    public function test_attach_list_does_not_leak_names_of_other_places_sharing_the_device(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner, 'Garagem A');
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $condominio = Place::create(['name' => 'CONDOMINIO SEGREDO']);
        $vizinho = Place::create(['name' => 'APTO DO VIZINHO']);
        $gate->places()->attach([$condominio->id, $vizinho->id]);

        $meuApto = $this->placeAdministeredBy($resident, 'Meu apto');

        $this->actingAs($resident)
            ->get("/app/places/{$meuApto->id}/devices/attach")
            ->assertOk()
            ->assertSee('Garagem A')
            ->assertDontSee('CONDOMINIO SEGREDO')
            ->assertDontSee('APTO DO VIZINHO');
    }

    private function deviceOwnedBy(User $user, string $name): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => $name,
            'brand' => 'portatec',
            'external_device_id' => 'chip-'.$user->id,
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }

    private function placeAdministeredBy(User $user, string $name = 'Meu local'): Place
    {
        $place = Place::create(['name' => $name]);

        PlaceUser::create(['place_id' => $place->id, 'user_id' => $user->id, 'role' => 'admin']);

        return $place;
    }
}
