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
    public function test_with_a_current_place_it_renders_that_place_control_panel(): void
    {
        [$user, $places] = $this->userWithPlaces();

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $places[0]->id]);

        $this->actingAs($user)
            ->get('/app/control')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('places/control')
                ->where('place.id', $places[0]->id)
            );
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
