<?php

declare(strict_types=1);

namespace Tests\Feature\AccessCodes;

use App\Enums\PlaceRoleEnum;
use App\Models\AccessCode;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use App\Services\AccessCodeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AccessCodesTest extends TestCase
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

    private function makeAccessCode(Place $place, array $overrides = []): AccessCode
    {
        return AccessCode::create(array_merge([
            'place_id' => $place->id,
            'user_id' => null,
            'booking_id' => null,
            'pin' => (string) random_int(100000, 999999),
            'start' => now()->subDay(),
            'end' => now()->addDay(),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // GET screens render the right Inertia component
    // ------------------------------------------------------------------

    public function test_index_renders_access_codes_index(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/access-codes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('access-codes/index'));
    }

    public function test_create_renders_access_codes_create(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/access-codes/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('access-codes/create'));
    }

    public function test_edit_renders_access_codes_edit(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $accessCode = $this->makeAccessCode($place);

        $this->actingAs($user)
            ->get("/app/access-codes/{$accessCode->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('access-codes/edit'));
    }

    // ------------------------------------------------------------------
    // IDOR: usuario nao ve/altera codigo de outra conta
    // ------------------------------------------------------------------

    public function test_index_does_not_list_other_users_access_codes(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $ownerCode = $this->makeAccessCode($ownerPlace);

        $intruder = User::factory()->create();
        $intruderPlace = $this->makePlaceWithAdmin($intruder, 'Outra Casa');
        $intruderCode = $this->makeAccessCode($intruderPlace);

        $response = $this->actingAs($intruder)->get('/app/access-codes');

        $response->assertInertia(function ($page) use ($ownerCode, $intruderCode) {
            $ids = collect($page->toArray()['props']['accessCodes']['data'])->pluck('id');
            $this->assertFalse($ids->contains($ownerCode->id));
            $this->assertTrue($ids->contains($intruderCode->id));
        });
    }

    public function test_edit_of_other_users_access_code_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $accessCode = $this->makeAccessCode($ownerPlace);

        $intruder = User::factory()->create();
        $this->makePlaceWithAdmin($intruder, 'Outra Casa');

        $this->actingAs($intruder)
            ->get("/app/access-codes/{$accessCode->id}/edit")
            ->assertForbidden();
    }

    public function test_update_of_other_users_access_code_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $accessCode = $this->makeAccessCode($ownerPlace);

        $intruder = User::factory()->create();
        $this->makePlaceWithAdmin($intruder, 'Outra Casa');

        $this->actingAs($intruder)
            ->put("/app/access-codes/{$accessCode->id}", [
                'pin' => '111111',
                'start' => now()->toDateTimeString(),
                'end' => now()->addDay()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('access_codes', ['id' => $accessCode->id, 'pin' => $accessCode->pin]);
    }

    // ------------------------------------------------------------------
    // Index: filtros de place, status (active/expired/future) e pin
    // ------------------------------------------------------------------

    public function test_index_filters_by_place(): void
    {
        $user = User::factory()->create();
        $placeA = $this->makePlaceWithAdmin($user, 'Casa A');
        $placeB = $this->makePlaceWithAdmin($user, 'Casa B');

        $codeA = $this->makeAccessCode($placeA);
        $codeB = $this->makeAccessCode($placeB);

        $response = $this->actingAs($user)->get("/app/access-codes?place_id={$placeA->id}");

        $response->assertInertia(function ($page) use ($codeA, $codeB) {
            $ids = collect($page->toArray()['props']['accessCodes']['data'])->pluck('id');
            $this->assertTrue($ids->contains($codeA->id));
            $this->assertFalse($ids->contains($codeB->id));
        });
    }

    public function test_index_filters_by_active_status(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $active = $this->makeAccessCode($place, [
            'start' => now()->subDay(),
            'end' => now()->addDay(),
        ]);
        $expired = $this->makeAccessCode($place, [
            'start' => now()->subDays(5),
            'end' => now()->subDays(2),
        ]);
        $future = $this->makeAccessCode($place, [
            'start' => now()->addDays(2),
            'end' => now()->addDays(5),
        ]);

        $response = $this->actingAs($user)->get('/app/access-codes?status=active');

        $response->assertInertia(function ($page) use ($active, $expired, $future) {
            $ids = collect($page->toArray()['props']['accessCodes']['data'])->pluck('id');
            $this->assertTrue($ids->contains($active->id));
            $this->assertFalse($ids->contains($expired->id));
            $this->assertFalse($ids->contains($future->id));
        });
    }

    public function test_index_filters_by_expired_status(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $active = $this->makeAccessCode($place, [
            'start' => now()->subDay(),
            'end' => now()->addDay(),
        ]);
        $expired = $this->makeAccessCode($place, [
            'start' => now()->subDays(5),
            'end' => now()->subDays(2),
        ]);
        $future = $this->makeAccessCode($place, [
            'start' => now()->addDays(2),
            'end' => now()->addDays(5),
        ]);

        $response = $this->actingAs($user)->get('/app/access-codes?status=expired');

        $response->assertInertia(function ($page) use ($active, $expired, $future) {
            $ids = collect($page->toArray()['props']['accessCodes']['data'])->pluck('id');
            $this->assertFalse($ids->contains($active->id));
            $this->assertTrue($ids->contains($expired->id));
            $this->assertFalse($ids->contains($future->id));
        });
    }

    public function test_index_filters_by_future_status(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $active = $this->makeAccessCode($place, [
            'start' => now()->subDay(),
            'end' => now()->addDay(),
        ]);
        $expired = $this->makeAccessCode($place, [
            'start' => now()->subDays(5),
            'end' => now()->subDays(2),
        ]);
        $future = $this->makeAccessCode($place, [
            'start' => now()->addDays(2),
            'end' => now()->addDays(5),
        ]);

        $response = $this->actingAs($user)->get('/app/access-codes?status=future');

        $response->assertInertia(function ($page) use ($active, $expired, $future) {
            $ids = collect($page->toArray()['props']['accessCodes']['data'])->pluck('id');
            $this->assertFalse($ids->contains($active->id));
            $this->assertFalse($ids->contains($expired->id));
            $this->assertTrue($ids->contains($future->id));
        });
    }

    public function test_index_filters_by_pin_search(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $match = $this->makeAccessCode($place, ['pin' => '123456']);
        $noMatch = $this->makeAccessCode($place, ['pin' => '999999']);

        $response = $this->actingAs($user)->get('/app/access-codes?search=1234');

        $response->assertInertia(function ($page) use ($match, $noMatch) {
            $ids = collect($page->toArray()['props']['accessCodes']['data'])->pluck('id');
            $this->assertTrue($ids->contains($match->id));
            $this->assertFalse($ids->contains($noMatch->id));
        });
    }

    // ------------------------------------------------------------------
    // store()
    // ------------------------------------------------------------------

    public function test_store_rejects_place_the_user_does_not_own(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser, 'Casa Alheia');

        $this->actingAs($user)
            ->post('/app/access-codes', [
                'placeId' => $foreignPlace->id,
                'start' => now()->toDateTimeString(),
                'end' => now()->addDay()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('access_codes', ['place_id' => $foreignPlace->id]);
    }

    public function test_store_with_pin_uses_exact_pin(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->post('/app/access-codes', [
                'placeId' => $place->id,
                'pin' => '135790',
                'start' => now()->toDateTimeString(),
                'end' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('access_codes', [
            'place_id' => $place->id,
            'pin' => '135790',
        ]);
    }

    public function test_store_without_pin_generates_six_digit_pin(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->post('/app/access-codes', [
                'placeId' => $place->id,
                'start' => now()->toDateTimeString(),
                'end' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();

        $accessCode = AccessCode::query()->where('place_id', $place->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $accessCode->pin);
    }

    public function test_store_redirects_to_edit_screen_of_new_access_code(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $response = $this->actingAs($user)->post('/app/access-codes', [
            'placeId' => $place->id,
            'start' => now()->toDateTimeString(),
            'end' => now()->addDay()->toDateTimeString(),
        ]);

        $accessCode = AccessCode::query()->where('place_id', $place->id)->firstOrFail();

        $response->assertRedirect("/app/access-codes/{$accessCode->id}/edit");
    }

    // ------------------------------------------------------------------
    // update()
    // ------------------------------------------------------------------

    public function test_update_persists_pin_start_and_end(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $accessCode = $this->makeAccessCode($place);

        $newStart = now()->addHours(2);
        $newEnd = now()->addDays(3);

        $this->actingAs($user)
            ->put("/app/access-codes/{$accessCode->id}", [
                'pin' => '246810',
                'start' => $newStart->toDateTimeString(),
                'end' => $newEnd->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('access_codes', [
            'id' => $accessCode->id,
            'pin' => '246810',
            'start' => $newStart->format('Y-m-d H:i:s'),
            'end' => $newEnd->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_update_does_not_redirect_to_index_but_stays_on_edit(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $accessCode = $this->makeAccessCode($place);

        $response = $this->actingAs($user)->put("/app/access-codes/{$accessCode->id}", [
            'pin' => '246810',
            'start' => now()->toDateTimeString(),
            'end' => now()->addDay()->toDateTimeString(),
        ]);

        $response->assertRedirect("/app/access-codes/{$accessCode->id}/edit");
    }

    // ------------------------------------------------------------------
    // Sincronizacao com as fechaduras: create/update devem disparar o
    // AccessCodeObserver (via AccessCodeSyncService), sob pena de PINs
    // pararem de chegar nas fechaduras fisicas.
    // ------------------------------------------------------------------

    public function test_store_triggers_access_code_sync_for_new_code(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $spy = Mockery::spy(AccessCodeSyncService::class);
        $this->app->instance(AccessCodeSyncService::class, $spy);

        $this->actingAs($user)
            ->post('/app/access-codes', [
                'placeId' => $place->id,
                'start' => now()->toDateTimeString(),
                'end' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();

        $spy->shouldHaveReceived('syncNewAccessCode')->once();
    }

    public function test_update_triggers_access_code_sync_for_updated_code(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $accessCode = $this->makeAccessCode($place);

        $spy = Mockery::spy(AccessCodeSyncService::class);
        $this->app->instance(AccessCodeSyncService::class, $spy);

        $this->actingAs($user)
            ->put("/app/access-codes/{$accessCode->id}", [
                'pin' => '135791',
                'start' => now()->toDateTimeString(),
                'end' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();

        $spy->shouldHaveReceived('syncUpdatedAccessCode')->once();
    }
}
