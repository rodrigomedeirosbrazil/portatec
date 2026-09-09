<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_device_admin_can_update_and_view(): void
    {
        $admin = User::factory()->create();
        $device = $this->deviceOwnedBy($admin);

        $this->assertTrue($admin->can('update', $device));
        $this->assertTrue($admin->can('view', $device));
    }

    public function test_grantee_can_view_but_not_update(): void
    {
        $admin = User::factory()->create();
        $grantee = User::factory()->create();
        $device = $this->deviceOwnedBy($admin);

        $device->deviceUsers()->create(['user_id' => $grantee->id, 'role' => 'user']);

        $this->assertTrue($grantee->can('view', $device));
        $this->assertFalse($grantee->can('update', $device));
    }

    public function test_place_member_can_view_but_not_update(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $device = $this->deviceOwnedBy($admin);

        $place = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $member->id, 'role' => 'host']);
        $device->places()->attach($place->id);

        $this->assertTrue($member->can('view', $device));
        $this->assertFalse($member->can('update', $device));
    }

    public function test_stranger_can_do_nothing(): void
    {
        $admin = User::factory()->create();
        $stranger = User::factory()->create();
        $device = $this->deviceOwnedBy($admin);

        $this->assertFalse($stranger->can('view', $device));
        $this->assertFalse($stranger->can('update', $device));
        $this->assertFalse($stranger->can('attach', $device));
    }

    public function test_tuya_device_without_place_is_not_public_to_other_tuya_users(): void
    {
        $owner = User::factory()->create();
        $otherTuyaUser = User::factory()->create();

        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Tuya solto',
            'brand' => 'tuya',
        ]));
        $device->deviceUsers()->create(['user_id' => $owner->id, 'role' => 'admin']);

        $platform = \App\Models\Platform::firstOrCreate(['slug' => 'tuya'], ['name' => 'Tuya']);
        \App\Models\Integration::create([
            'user_id' => $otherTuyaUser->id,
            'platform_id' => $platform->id,
        ]);

        $this->assertFalse($otherTuyaUser->can('view', $device));
    }

    public function test_grantee_and_admin_can_attach_but_place_member_cannot(): void
    {
        $admin = User::factory()->create();
        $grantee = User::factory()->create();
        $member = User::factory()->create();

        $device = $this->deviceOwnedBy($admin);
        $device->deviceUsers()->create(['user_id' => $grantee->id, 'role' => 'user']);

        $place = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $member->id, 'role' => 'admin']);
        $device->places()->attach($place->id);

        $this->assertTrue($admin->can('attach', $device));
        $this->assertTrue($grantee->can('attach', $device));
        $this->assertFalse($member->can('attach', $device));
    }

    public function test_edit_screen_is_closed_to_grantee_and_place_member(): void
    {
        $admin = User::factory()->create();
        $grantee = User::factory()->create();
        $member = User::factory()->create();

        $device = $this->deviceOwnedBy($admin);
        $device->deviceUsers()->create(['user_id' => $grantee->id, 'role' => 'user']);

        $place = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $member->id, 'role' => 'admin']);
        $device->places()->attach($place->id);

        // A tela de edição expõe pinos e external_device_id: é configuração,
        // não uso. Só o admin do dispositivo entra.
        $this->actingAs($grantee)->get("/app/devices/{$device->id}/edit")->assertForbidden();
        $this->actingAs($member)->get("/app/devices/{$device->id}/edit")->assertForbidden();
        $this->actingAs($admin)->get("/app/devices/{$device->id}/edit")->assertOk();
    }

    public function test_grantee_does_not_see_names_of_other_places_sharing_the_device(): void
    {
        $admin = User::factory()->create();
        $grantee = User::factory()->create();

        $device = $this->deviceOwnedBy($admin);
        $device->deviceUsers()->create(['user_id' => $grantee->id, 'role' => 'user']);

        $condominio = Place::create(['name' => 'CONDOMINIO SEGREDO']);
        $vizinho = Place::create(['name' => 'APTO DO VIZINHO']);
        $meuApto = Place::create(['name' => 'Meu apto']);
        PlaceUser::create(['place_id' => $meuApto->id, 'user_id' => $grantee->id, 'role' => 'admin']);

        $device->places()->attach([$condominio->id, $vizinho->id, $meuApto->id]);

        // Um portao dividido por 16 unidades nao pode contar a cada morador o
        // nome da unidade dos outros 15 - mesmo vazamento que o spec §8 fecha
        // no historico de acesso.
        $this->actingAs($grantee)
            ->get('/app/devices')
            ->assertOk()
            ->assertDontSee('CONDOMINIO SEGREDO')
            ->assertDontSee('APTO DO VIZINHO')
            ->assertSee('Meu apto');

        // O admin do dispositivo continua vendo todos os locais.
        $this->actingAs($admin)
            ->get("/app/devices/{$device->id}")
            ->assertOk()
            ->assertSee('CONDOMINIO SEGREDO');
    }

    private function deviceOwnedBy(User $user): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }
}
