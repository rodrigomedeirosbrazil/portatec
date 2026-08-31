<?php

declare(strict_types=1);

namespace Tests\Feature\Bookings;

use App\Enums\PlaceRoleEnum;
use App\Models\Booking;
use App\Models\Integration;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingsTest extends TestCase
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

    private function makeBooking(Place $place, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'place_id' => $place->id,
            'guest_name' => 'Hospede Teste',
            'check_in' => now()->addDay(),
            'check_out' => now()->addDays(3),
            'source' => 'manual',
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // GET screens render the right Inertia component
    // ------------------------------------------------------------------

    public function test_index_renders_bookings_index(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/bookings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('bookings/index'));
    }

    public function test_create_renders_bookings_create(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/bookings/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('bookings/create'));
    }

    public function test_show_renders_bookings_show(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $booking = $this->makeBooking($place);

        $this->actingAs($user)
            ->get("/app/bookings/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('bookings/show'));
    }

    // ------------------------------------------------------------------
    // IDOR: usuario nao acessa nem altera reserva de outra conta
    // ------------------------------------------------------------------

    public function test_show_of_other_users_booking_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $booking = $this->makeBooking($ownerPlace);

        $intruder = User::factory()->create();
        $this->makePlaceWithAdmin($intruder, 'Outra Casa');

        $this->actingAs($intruder)
            ->get("/app/bookings/{$booking->id}")
            ->assertForbidden();
    }

    public function test_destroy_of_other_users_booking_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $ownerPlace = $this->makePlaceWithAdmin($owner);
        $booking = $this->makeBooking($ownerPlace);

        $intruder = User::factory()->create();
        $this->makePlaceWithAdmin($intruder, 'Outra Casa');

        $this->actingAs($intruder)
            ->delete("/app/bookings/{$booking->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deleted_at' => null]);
    }

    // ------------------------------------------------------------------
    // Index: padrao 'future' vs ausencia do padrao com outro filtro
    // ------------------------------------------------------------------

    public function test_index_defaults_to_future_bookings_when_no_other_filter(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $past = $this->makeBooking($place, [
            'guest_name' => 'Passado',
            'check_in' => now()->subDays(10),
            'check_out' => now()->subDays(8),
        ]);
        $future = $this->makeBooking($place, [
            'guest_name' => 'Futuro',
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get('/app/bookings');

        $response->assertInertia(function ($page) use ($past, $future) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($future->id));
            $this->assertFalse($ids->contains($past->id));
        });
    }

    public function test_index_does_not_default_to_future_when_another_filter_is_present(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $past = $this->makeBooking($place, [
            'guest_name' => 'Zezinho',
            'check_in' => now()->subDays(10),
            'check_out' => now()->subDays(8),
        ]);
        $future = $this->makeBooking($place, [
            'guest_name' => 'Outro Hospede',
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get('/app/bookings?guest=Zezinho');

        $response->assertInertia(function ($page) use ($past, $future) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($past->id));
            $this->assertFalse($ids->contains($future->id));
        });
    }

    // ------------------------------------------------------------------
    // Index: filtros de place, periodo, status, hospede e origem
    // ------------------------------------------------------------------

    public function test_index_filters_by_place(): void
    {
        $user = User::factory()->create();
        $placeA = $this->makePlaceWithAdmin($user, 'Casa A');
        $placeB = $this->makePlaceWithAdmin($user, 'Casa B');

        $bookingA = $this->makeBooking($placeA);
        $bookingB = $this->makeBooking($placeB);

        $response = $this->actingAs($user)->get("/app/bookings?place_id={$placeA->id}");

        $response->assertInertia(function ($page) use ($bookingA, $bookingB) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($bookingA->id));
            $this->assertFalse($ids->contains($bookingB->id));
        });
    }

    public function test_index_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $inRange = $this->makeBooking($place, [
            'check_in' => now()->addDays(2),
            'check_out' => now()->addDays(4),
        ]);
        $outOfRange = $this->makeBooking($place, [
            'check_in' => now()->addDays(20),
            'check_out' => now()->addDays(22),
        ]);

        $dateFrom = now()->addDay()->toDateString();
        $dateTo = now()->addDays(10)->toDateString();

        $response = $this->actingAs($user)->get("/app/bookings?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertInertia(function ($page) use ($inRange, $outOfRange) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($inRange->id));
            $this->assertFalse($ids->contains($outOfRange->id));
        });
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $current = $this->makeBooking($place, [
            'check_in' => now()->subDay(),
            'check_out' => now()->addDay(),
        ]);
        $future = $this->makeBooking($place, [
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get('/app/bookings?status=current');

        $response->assertInertia(function ($page) use ($current, $future) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($current->id));
            $this->assertFalse($ids->contains($future->id));
        });
    }

    public function test_index_filters_by_guest_name(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $match = $this->makeBooking($place, ['guest_name' => 'Maria Silva']);
        $noMatch = $this->makeBooking($place, ['guest_name' => 'Joao Souza']);

        $response = $this->actingAs($user)->get('/app/bookings?guest=Maria');

        $response->assertInertia(function ($page) use ($match, $noMatch) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($match->id));
            $this->assertFalse($ids->contains($noMatch->id));
        });
    }

    public function test_index_filters_by_source(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $platform = Platform::create(['name' => 'Airbnb', 'slug' => 'airbnb']);
        $integration = Integration::create(['platform_id' => $platform->id, 'user_id' => $user->id]);

        $manual = $this->makeBooking($place, ['source' => 'manual']);
        $ical = $this->makeBooking($place, ['source' => 'ical', 'integration_id' => $integration->id]);

        $response = $this->actingAs($user)->get('/app/bookings?source=manual');

        $response->assertInertia(function ($page) use ($manual, $ical) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($manual->id));
            $this->assertFalse($ids->contains($ical->id));
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
            ->post('/app/bookings', [
                'placeId' => $foreignPlace->id,
                'guestName' => 'Fulano',
                'checkIn' => now()->addDay()->toDateTimeString(),
                'checkOut' => now()->addDays(2)->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('bookings', ['place_id' => $foreignPlace->id]);
    }

    public function test_store_creates_manual_booking(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->post('/app/bookings', [
                'placeId' => $place->id,
                'guestName' => 'Fulano',
                'checkIn' => now()->addDay()->toDateTimeString(),
                'checkOut' => now()->addDays(2)->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'place_id' => $place->id,
            'guest_name' => 'Fulano',
            'source' => 'manual',
        ]);
    }

    // ------------------------------------------------------------------
    // destroy(): so manual pode ser apagada
    // ------------------------------------------------------------------

    public function test_cannot_delete_ical_booking(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $platform = Platform::create(['name' => 'Airbnb', 'slug' => 'airbnb']);
        $integration = Integration::create(['platform_id' => $platform->id, 'user_id' => $user->id]);

        $booking = $this->makeBooking($place, [
            'source' => 'ical',
            'integration_id' => $integration->id,
        ]);

        $this->actingAs($user)
            ->delete("/app/bookings/{$booking->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deleted_at' => null]);
    }

    public function test_can_delete_manual_booking(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $booking = $this->makeBooking($place, ['source' => 'manual']);

        $this->actingAs($user)
            ->delete("/app/bookings/{$booking->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
    }
}
