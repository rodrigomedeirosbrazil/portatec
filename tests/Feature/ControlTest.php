<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlaces(int $count = 2): array
    {
        $user = User::factory()->create();
        $places = [];

        foreach (range(1, $count) as $index) {
            $place = Place::create(['name' => "Local {$index}"]);
            PlaceUser::create([
                'place_id' => $place->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'label' => $user->name,
            ]);
            $places[] = $place;
        }

        return [$user, $places];
    }

    public function test_without_a_current_place_it_lists_the_places(): void
    {
        [$user, $places] = $this->userWithPlaces();

        $this->actingAs($user)
            ->get('/app/control')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('control/index')
                ->has('places', count($places))
            );
    }

    /**
     * O retorno prático de existir um local atual: com um selecionado, abrir uma
     * porta é um clique, não três.
     */
    /**
     * `/app/control` e SEMPRE a lista, mesmo com um local atual definido. Ja foi
     * polimorfica — painel quando havia local atual, lista quando nao — e isso se
     * mostrou errado: o breadcrumb do painel aponta para `/app/control` como pai,
     * e com a rota polimorfica esse link levava de volta a propria pagina.
     *
     * O atalho de um clique nao se perdeu: vive no `href` do item "Controle" da
     * sidebar, que aponta direto ao painel do local atual.
     */
    public function test_with_a_current_place_it_still_lists_the_places(): void
    {
        [$user, $places] = $this->userWithPlaces();

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $places[0]->id]);

        $this->actingAs($user)
            ->get('/app/control')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('control/index')
                ->has('places', count($places))
            );
    }

    /**
     * Regra de precedencia aplicada ao painel: abrir o link direto de um local
     * torna esse local o atual. Sem isso, um favorito antigo como
     * `/app/places/2/control` mostraria o local 2 enquanto o seletor do topo e o
     * item "Controle" da sidebar continuariam apontando para outro lugar.
     */
    public function test_opening_a_place_control_panel_makes_that_place_current(): void
    {
        [$user, $places] = $this->userWithPlaces();

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $places[1]->id]);

        $this->actingAs($user)
            ->get("/app/places/{$places[0]->id}/control")
            ->assertOk();

        $this->assertSame($places[0]->id, session('current_place_id'));
    }

    /**
     * O `set()` do servico revalida o vinculo, entao um local de outra conta nem
     * chega a virar atual — o 403 vem antes, e a sessao fica intacta.
     */
    public function test_a_forbidden_place_does_not_become_current(): void
    {
        [$user, $places] = $this->userWithPlaces();
        $foreign = Place::create(['name' => 'De outro']);

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $places[0]->id]);

        $this->actingAs($user)
            ->get("/app/places/{$foreign->id}/control")
            ->assertForbidden();

        $this->assertSame($places[0]->id, session('current_place_id'));
    }

    public function test_it_only_lists_places_the_user_belongs_to(): void
    {
        [$user] = $this->userWithPlaces(1);
        Place::create(['name' => 'De outro']);

        $this->actingAs($user)
            ->get('/app/control')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('places', 1));
    }
}
