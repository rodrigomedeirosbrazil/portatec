<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceBrandEnum;
use App\Enums\PlaceRoleEnum;
use App\Livewire\Devices\Index;
use App\Models\Device;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DevicesIndexScopingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regressão de isolamento entre contas: um dispositivo importado por outro usuário e
     * ainda sem local vinculado não pode aparecer na listagem de terceiros.
     *
     * Ver AGENTS.md §4 — toda consulta do app do cliente é escopada pelos places do usuário.
     */
    public function test_it_does_not_leak_unassigned_devices_from_another_account(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = Place::create(['name' => 'Casa do dono']);
        $owner->placeUsers()->create(['place_id' => $ownerPlace->id, 'role' => PlaceRoleEnum::Admin]);

        // Dispositivo recém importado pelo dono: sem local ainda, vinculado a ele por device_user.
        $orphan = Device::create([
            'name' => 'Fechadura recem importada',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'tuya-orphan',
        ]);
        $orphan->deviceUsers()->create(['user_id' => $owner->id]);

        $stranger = User::factory()->create();
        $strangerPlace = Place::create(['name' => 'Casa do estranho']);
        $stranger->placeUsers()->create(['place_id' => $strangerPlace->id, 'role' => PlaceRoleEnum::Admin]);

        $this->actingAs($stranger);

        Livewire::test(Index::class)->assertDontSee('Fechadura recem importada');
    }

    public function test_the_owner_still_sees_their_own_unassigned_device(): void
    {
        $owner = User::factory()->create();
        $place = Place::create(['name' => 'Casa do dono']);
        $owner->placeUsers()->create(['place_id' => $place->id, 'role' => PlaceRoleEnum::Admin]);

        $orphan = Device::create([
            'name' => 'Fechadura recem importada',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'tuya-orphan',
        ]);
        $orphan->deviceUsers()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);

        Livewire::test(Index::class)->assertSee('Fechadura recem importada');
    }
}
