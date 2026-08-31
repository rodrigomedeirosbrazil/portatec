<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\PlaceRoleEnum;
use App\Jobs\SyncIntegrationBookingsJob;
use App\Models\Integration;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IntegrationsTest extends TestCase
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

    private function airbnbPlatform(): Platform
    {
        return Platform::firstOrCreate(['slug' => 'airbnb'], ['name' => 'Airbnb']);
    }

    private function tuyaPlatform(): Platform
    {
        return Platform::firstOrCreate(['slug' => 'tuya'], ['name' => 'Tuya']);
    }

    private function makeIntegration(User $user, Platform $platform, Place $place, string $externalId = 'https://example.test/calendar.ics'): Integration
    {
        $integration = Integration::create([
            'platform_id' => $platform->id,
            'user_id' => $user->id,
        ]);

        $integration->places()->attach($place->id, ['external_id' => $externalId]);

        return $integration;
    }

    // ------------------------------------------------------------------
    // GET screens render the right Inertia component
    // ------------------------------------------------------------------

    public function test_index_renders_integrations_index(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/bookings/integrations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('integrations/index'));
    }

    public function test_create_renders_integrations_create(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);
        $this->airbnbPlatform();

        $this->actingAs($user)
            ->get('/app/bookings/integrations/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('integrations/create'));
    }

    public function test_edit_renders_integrations_edit(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $integration = $this->makeIntegration($user, $this->airbnbPlatform(), $place);

        $this->actingAs($user)
            ->get("/app/bookings/integrations/{$integration->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('integrations/edit'));
    }

    // ------------------------------------------------------------------
    // Escopo: sem integracoes tuya na listagem, e edit de tuya -> 404
    // ------------------------------------------------------------------

    public function test_index_excludes_tuya_integrations(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $icalIntegration = $this->makeIntegration($user, $this->airbnbPlatform(), $place);
        $tuyaIntegration = Integration::create([
            'platform_id' => $this->tuyaPlatform()->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/app/bookings/integrations');

        $response->assertInertia(function ($page) use ($icalIntegration, $tuyaIntegration) {
            $ids = collect($page->toArray()['props']['integrations'])->pluck('id');
            $this->assertTrue($ids->contains($icalIntegration->id));
            $this->assertFalse($ids->contains($tuyaIntegration->id));
        });
    }

    public function test_edit_of_tuya_integration_is_not_found(): void
    {
        $user = User::factory()->create();
        $tuyaIntegration = Integration::create([
            'platform_id' => $this->tuyaPlatform()->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get("/app/bookings/integrations/{$tuyaIntegration->id}/edit")
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // IDOR
    // ------------------------------------------------------------------

    public function test_edit_of_other_users_integration_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $integration = $this->makeIntegration($owner, $this->airbnbPlatform(), $ownerPlace);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get("/app/bookings/integrations/{$integration->id}/edit")
            ->assertForbidden();
    }

    public function test_destroy_of_other_users_integration_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $integration = $this->makeIntegration($owner, $this->airbnbPlatform(), $ownerPlace);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->delete("/app/bookings/integrations/{$integration->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('integrations', ['id' => $integration->id, 'deleted_at' => null]);
    }

    public function test_update_external_id_of_other_users_integration_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $integration = $this->makeIntegration($owner, $this->airbnbPlatform(), $ownerPlace);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->put("/app/bookings/integrations/{$integration->id}/places/{$ownerPlace->id}", [
                'externalId' => 'https://example.test/other.ics',
            ])
            ->assertForbidden();
    }

    public function test_update_external_id_with_a_place_not_owned_by_the_user_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $integration = $this->makeIntegration($owner, $this->airbnbPlatform(), $ownerPlace);

        $intruder = User::factory()->create();
        $intruderPlace = $this->makePlaceWithAdmin($intruder, 'Casa do Intruso');

        // Intruder owns the place but not the integration: still forbidden.
        $this->actingAs($intruder)
            ->put("/app/bookings/integrations/{$integration->id}/places/{$intruderPlace->id}", [
                'externalId' => 'https://example.test/other.ics',
            ])
            ->assertForbidden();
    }

    public function test_remove_place_of_other_users_integration_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $integration = $this->makeIntegration($owner, $this->airbnbPlatform(), $ownerPlace);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->delete("/app/bookings/integrations/{$integration->id}/places/{$ownerPlace->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('place_integration', [
            'integration_id' => $integration->id,
            'place_id' => $ownerPlace->id,
        ]);
    }

    // ------------------------------------------------------------------
    // store() dispatches SyncIntegrationBookingsJob
    // ------------------------------------------------------------------

    public function test_store_dispatches_sync_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $platform = $this->airbnbPlatform();

        $this->actingAs($user)
            ->post('/app/bookings/integrations', [
                'platformId' => $platform->id,
                'placeId' => $place->id,
                'externalId' => 'https://example.test/calendar.ics',
            ])
            ->assertRedirect();

        Queue::assertPushed(SyncIntegrationBookingsJob::class);
    }

    public function test_store_rejects_place_the_user_does_not_own(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser, 'Casa Alheia');

        $platform = $this->airbnbPlatform();

        $this->actingAs($user)
            ->post('/app/bookings/integrations', [
                'platformId' => $platform->id,
                'placeId' => $foreignPlace->id,
                'externalId' => 'https://example.test/calendar.ics',
            ])
            ->assertForbidden();

        Queue::assertNotPushed(SyncIntegrationBookingsJob::class);
    }

    // ------------------------------------------------------------------
    // Validacao Airbnb: criacao
    // ------------------------------------------------------------------

    public function test_store_rejects_reservation_details_url(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $platform = $this->airbnbPlatform();

        $this->actingAs($user)
            ->post('/app/bookings/integrations', [
                'platformId' => $platform->id,
                'placeId' => $place->id,
                'externalId' => 'https://www.airbnb.com/hosting/reservations/details/HM2AHARRC4',
            ])
            ->assertSessionHasErrors('externalId');
    }

    public function test_store_rejects_url_not_ending_in_ics(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $platform = $this->airbnbPlatform();

        $this->actingAs($user)
            ->post('/app/bookings/integrations', [
                'platformId' => $platform->id,
                'placeId' => $place->id,
                'externalId' => 'https://example.test/not-a-calendar',
            ])
            ->assertSessionHasErrors('externalId');
    }

    // ------------------------------------------------------------------
    // Validacao Airbnb: atualizacao do external_id
    // ------------------------------------------------------------------

    public function test_update_external_id_rejects_reservation_details_url(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $integration = $this->makeIntegration($user, $this->airbnbPlatform(), $place);

        $this->actingAs($user)
            ->put("/app/bookings/integrations/{$integration->id}/places/{$place->id}", [
                'externalId' => 'https://www.airbnb.com/hosting/reservations/details/HM2AHARRC4',
            ])
            ->assertSessionHasErrors('externalId');
    }

    public function test_update_external_id_rejects_url_not_ending_in_ics(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $integration = $this->makeIntegration($user, $this->airbnbPlatform(), $place);

        $this->actingAs($user)
            ->put("/app/bookings/integrations/{$integration->id}/places/{$place->id}", [
                'externalId' => 'https://example.test/not-a-calendar',
            ])
            ->assertSessionHasErrors('externalId');
    }

    public function test_update_external_id_accepts_ics_url(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $integration = $this->makeIntegration($user, $this->airbnbPlatform(), $place);

        $this->actingAs($user)
            ->put("/app/bookings/integrations/{$integration->id}/places/{$place->id}", [
                'externalId' => 'https://example.test/updated.ics',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('place_integration', [
            'integration_id' => $integration->id,
            'place_id' => $place->id,
            'external_id' => 'https://example.test/updated.ics',
        ]);
    }

    // ------------------------------------------------------------------
    // Remocao de place: ultimo place apaga a integracao
    // ------------------------------------------------------------------

    public function test_removing_last_place_deletes_the_integration(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $integration = $this->makeIntegration($user, $this->airbnbPlatform(), $place);

        $this->actingAs($user)
            ->delete("/app/bookings/integrations/{$integration->id}/places/{$place->id}")
            ->assertRedirect(route('app.bookings.integrations.index'));

        $this->assertDatabaseMissing('integrations', ['id' => $integration->id, 'deleted_at' => null]);
    }

    public function test_removing_one_of_several_places_only_detaches_it(): void
    {
        $user = User::factory()->create();
        $placeA = $this->makePlaceWithAdmin($user, 'Casa A');
        $placeB = $this->makePlaceWithAdmin($user, 'Casa B');

        $integration = $this->makeIntegration($user, $this->airbnbPlatform(), $placeA);
        $integration->places()->attach($placeB->id, ['external_id' => 'https://example.test/b.ics']);

        $this->actingAs($user)
            ->delete("/app/bookings/integrations/{$integration->id}/places/{$placeA->id}")
            ->assertRedirect(route('app.bookings.integrations.edit', ['integration' => $integration->id]));

        $this->assertDatabaseHas('integrations', ['id' => $integration->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('place_integration', [
            'integration_id' => $integration->id,
            'place_id' => $placeA->id,
        ]);
        $this->assertDatabaseHas('place_integration', [
            'integration_id' => $integration->id,
            'place_id' => $placeB->id,
        ]);
    }
}
