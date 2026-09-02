<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class IntegrationCreatePrefillTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlaces(): array
    {
        $user = User::factory()->create();
        Platform::create(['name' => 'Airbnb', 'slug' => 'airbnb']);

        // Nomes escolhidos para que "Alfa" seja o primeiro na ordenação: sem o
        // parâmetro, é ele que vem pré-selecionado.
        $first = Place::create(['name' => 'Alfa']);
        $second = Place::create(['name' => 'Beta']);

        foreach ([$first, $second] as $place) {
            PlaceUser::create([
                'place_id' => $place->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'label' => $user->name,
            ]);
        }

        return [$user, $first, $second];
    }

    public function test_place_id_in_the_query_preselects_that_place(): void
    {
        [$user, , $second] = $this->userWithPlaces();

        $this->actingAs($user)
            ->get("/app/bookings/integrations/create?place_id={$second->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('integrations/create')
                ->where('placeId', $second->id)
            );
    }

    public function test_without_the_parameter_the_first_place_is_preselected(): void
    {
        [$user, $first] = $this->userWithPlaces();

        $this->actingAs($user)
            ->get('/app/bookings/integrations/create')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('placeId', $first->id));
    }

    /**
     * O parâmetro é conveniência de navegação, não autorização: um local de
     * outra conta é ignorado, e cai no padrão.
     */
    public function test_a_place_from_another_account_is_ignored(): void
    {
        [$user, $first] = $this->userWithPlaces();
        $foreign = Place::create(['name' => 'De outro']);

        $this->actingAs($user)
            ->get("/app/bookings/integrations/create?place_id={$foreign->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('placeId', $first->id));
    }
}
