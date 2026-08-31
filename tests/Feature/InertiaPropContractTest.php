<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Contrato de props entre os controllers e as paginas React.
 *
 * Os componentes declaram colecoes como array (`places: Place[]`, e chamam .map()
 * direto) e paginadores como `Paginated<T>` (data/links/meta). Por padrao, porem, o
 * JsonResource do Laravel envelopa colecoes em {"data": [...]}, o que faz o .map()
 * estourar no navegador com "e.map is not a function".
 *
 * Pior: com a colecao vazia, {"data": []} nao tem .length, o componente cai no estado
 * vazio e o defeito passa despercebido - a tela mostra "nenhum registro" mesmo havendo
 * dados. Foi exatamente o que aconteceu na tela de Locais.
 *
 * Estes testes fixam o contrato para que a divergencia falhe aqui, e nao no navegador.
 */
class InertiaPropContractTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlace(): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Local de Contrato']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return [$user, $place];
    }

    public function test_simple_collections_arrive_as_arrays_not_wrapped_in_data(): void
    {
        [$user] = $this->userWithPlace();

        $this->actingAs($user)->get('/app/places')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('places/index')
                ->has('places', 1)
                ->has('places.0.id')
        );
    }

    public function test_the_place_the_user_owns_actually_shows_up_in_the_listing(): void
    {
        [$user, $place] = $this->userWithPlace();

        $this->actingAs($user)->get('/app/places')->assertInertia(
            fn (AssertableInertia $page) => $page->where('places.0.name', $place->name)
        );
    }

    public function test_select_collections_on_other_screens_are_arrays_too(): void
    {
        [$user] = $this->userWithPlace();

        $this->actingAs($user)->get('/app/bookings')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('bookings/index')
                ->has('places', 1)
                ->has('places.0.id')
        );

        $this->actingAs($user)->get('/app/devices')->assertInertia(
            fn (AssertableInertia $page) => $page->has('places.0.id')
        );
    }

    public function test_paginated_collections_keep_data_and_meta(): void
    {
        [$user] = $this->userWithPlace();

        $this->actingAs($user)->get('/app/bookings')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('bookings.data')
                ->has('bookings.meta')
        );

        $this->actingAs($user)->get('/app/devices')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('devices.data')
                ->has('devices.meta')
        );
    }
}
