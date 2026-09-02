<?php

declare(strict_types=1);

namespace Tests\Feature\AccessCodes;

use App\Enums\PlaceRoleEnum;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use App\Services\CurrentPlaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessCodesCurrentPlaceTest extends TestCase
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

    public function test_index_uses_the_current_place_from_session_as_filter(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $this->actingAs($user);

        app(CurrentPlaceService::class)->set($user, $place->id);

        $this->get('/app/access-codes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('access-codes/index')
                ->where('filters.place_id', $place->id));
    }

    public function test_explicit_place_id_query_param_updates_the_session(): void
    {
        $user = User::factory()->create();
        $placeA = $this->makePlaceWithAdmin($user, 'Casa A');
        $placeB = $this->makePlaceWithAdmin($user, 'Casa B');
        $this->actingAs($user);

        app(CurrentPlaceService::class)->set($user, $placeA->id);

        $this->get('/app/access-codes?place_id='.$placeB->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('access-codes/index')
                ->where('filters.place_id', $placeB->id));

        $this->assertSame($placeB->id, app(CurrentPlaceService::class)->get($user->fresh())?->id);
    }

    public function test_a_place_id_the_user_has_no_access_to_is_ignored(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);
        $foreign = Place::create(['name' => 'De outro usuário']);
        $this->actingAs($user);

        $this->get('/app/access-codes?place_id='.$foreign->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('access-codes/index')
                ->where('filters.place_id', null));
    }
}
