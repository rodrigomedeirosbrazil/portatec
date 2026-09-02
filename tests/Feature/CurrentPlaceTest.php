<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use App\Services\CurrentPlaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CurrentPlaceTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlace(string $name = 'Casa Azul'): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => $name]);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return [$user, $place];
    }

    public function test_setting_and_reading_the_current_place(): void
    {
        [$user, $place] = $this->userWithPlace();
        $this->actingAs($user);

        app(CurrentPlaceService::class)->set($user, $place->id);

        $this->assertSame($place->id, app(CurrentPlaceService::class)->get($user)?->id);
    }

    public function test_null_clears_the_selection(): void
    {
        [$user, $place] = $this->userWithPlace();
        $this->actingAs($user);

        $service = app(CurrentPlaceService::class);
        $service->set($user, $place->id);
        $service->set($user, null);

        $this->assertNull($service->get($user));
    }

    public function test_a_place_from_another_account_is_rejected(): void
    {
        [$user] = $this->userWithPlace();
        $foreign = Place::create(['name' => 'De outro']);
        $this->actingAs($user);

        app(CurrentPlaceService::class)->set($user, $foreign->id);

        $this->assertNull(app(CurrentPlaceService::class)->get($user));
    }

    /**
     * O ponto crítico de segurança: sem revalidar o vínculo a cada leitura, um
     * usuário removido de um local continuaria vendo os dados dele até trocar de
     * seleção. Ver `PlaceUsersIsolationTest` para a mesma classe de defeito.
     */
    public function test_losing_access_to_the_place_clears_the_selection(): void
    {
        [$user, $place] = $this->userWithPlace();
        $this->actingAs($user);

        app(CurrentPlaceService::class)->set($user, $place->id);

        PlaceUser::where('place_id', $place->id)->where('user_id', $user->id)->delete();

        $this->assertNull(app(CurrentPlaceService::class)->get($user->fresh()));
        $this->assertFalse(session()->has('current_place_id'));
    }

    public function test_the_route_updates_the_selection(): void
    {
        [$user, $place] = $this->userWithPlace();

        $this->actingAs($user)
            ->from('/app/bookings')
            ->post('/app/current-place', ['place_id' => $place->id])
            ->assertRedirect('/app/bookings');

        $this->assertSame($place->id, session('current_place_id'));
    }

    public function test_shared_props_expose_the_current_place_and_the_place_list(): void
    {
        [$user, $place] = $this->userWithPlace();

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $place->id]);

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentPlace.id', $place->id)
                ->where('currentPlace.name', 'Casa Azul')
                ->has('places', 1)
            );
    }

    /**
     * Regra de precedência (spec 4.4): um place_id explícito e válido na URL
     * atualiza a sessão, para que o seletor nunca mostre um local diferente do
     * que a lista está exibindo.
     */
    public function test_an_explicit_place_id_in_the_url_updates_the_session(): void
    {
        [$user, $place] = $this->userWithPlace();

        $this->actingAs($user)->get("/app/bookings?place_id={$place->id}");

        $this->assertSame($place->id, session('current_place_id'));
    }
}
