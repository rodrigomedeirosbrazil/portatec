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

class DevicesIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $place = Place::create(['name' => 'Casa 1']);
        $this->user->placeUsers()->create(['place_id' => $place->id, 'role' => PlaceRoleEnum::Admin]);

        $attached = Device::create([
            'name' => 'Aparelho com local',
            'brand' => DeviceBrandEnum::Portatec,
            'external_device_id' => 'chip-1',
        ]);
        $attached->places()->attach($place->id);

        Device::create([
            'name' => 'Aparelho orfao',
            'brand' => DeviceBrandEnum::Portatec,
            'external_device_id' => 'chip-2',
            'place_id' => null,
        ]);

        $this->actingAs($this->user);
    }

    public function test_it_renders_the_unassigned_option_in_the_place_filter(): void
    {
        Livewire::test(Index::class)->assertSee('Sem local');
    }

    /**
     * O filtro não filtra em memória: updatedPlaceId() redireciona e o mount() relê o
     * place_id da query string. Por isso o teste verifica o destino do redirect.
     */
    public function test_choosing_unassigned_redirects_carrying_the_filter_in_the_url(): void
    {
        Livewire::test(Index::class)
            ->set('placeId', 'unassigned')
            ->assertRedirect(route('app.devices.index', ['place_id' => 'unassigned']));
    }

    public function test_the_unassigned_filter_shows_only_devices_without_a_place(): void
    {
        $this->get(route('app.devices.index', ['place_id' => 'unassigned']))
            ->assertOk()
            ->assertSee('Aparelho orfao')
            ->assertDontSee('Aparelho com local');
    }

    public function test_without_the_filter_both_devices_are_listed(): void
    {
        $this->get(route('app.devices.index'))
            ->assertOk()
            ->assertSee('Aparelho orfao')
            ->assertSee('Aparelho com local');
    }
}
