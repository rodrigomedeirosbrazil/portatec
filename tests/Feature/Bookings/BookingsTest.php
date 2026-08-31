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
    // Index: estado padrao (todos os status, janela de 7 dias) e o desvio
    // dessa janela quando a requisicao traz algum filtro
    // ------------------------------------------------------------------

    /**
     * O padrao da tela nao filtra por status: a reserva em andamento tem de
     * aparecer, e nao apenas as futuras. O unico recorte e a janela de
     * `hoje - 7 dias`, com semantica de sobreposicao.
     */
    public function test_index_defaults_to_all_statuses_within_the_last_week_window(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $current = $this->makeBooking($place, [
            'guest_name' => 'Em Andamento',
            'check_in' => now()->subDay(),
            'check_out' => now()->addDay(),
        ]);
        $future = $this->makeBooking($place, [
            'guest_name' => 'Futuro',
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);
        $recentPast = $this->makeBooking($place, [
            'guest_name' => 'Passado Recente',
            'check_in' => now()->subDays(4),
            'check_out' => now()->subDays(2),
        ]);
        $oldPast = $this->makeBooking($place, [
            'guest_name' => 'Passado Antigo',
            'check_in' => now()->subDays(10),
            'check_out' => now()->subDays(8),
        ]);

        $response = $this->actingAs($user)->get('/app/bookings');

        $response->assertInertia(function ($page) use ($current, $future, $recentPast, $oldPast) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($current->id), 'a reserva em andamento deve aparecer no padrao');
            $this->assertTrue($ids->contains($future->id));
            $this->assertTrue($ids->contains($recentPast->id), 'concluida dentro da janela deve aparecer');
            $this->assertFalse($ids->contains($oldPast->id), 'concluida fora da janela nao deve aparecer');
        });
    }

    /**
     * O prop `filters.status` alimenta o select da FilterBar: se o backend
     * devolver um valor que nao existe entre as opcoes do select, ele
     * renderiza vazio. Este teste fixa o contrato dos dois lados.
     */
    public function test_index_exposes_all_as_the_default_status_filter(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $response = $this->actingAs($user)->get('/app/bookings');

        $response->assertInertia(function ($page) {
            $this->assertSame('all', $page->toArray()['props']['filters']['status']);
        });
    }

    /**
     * A janela padrao e o estado inicial da tela, nao um recorte implicito
     * sobre uma busca: com qualquer filtro na requisicao ela nao se aplica,
     * e a busca por hospede varre todo o historico.
     */
    public function test_index_does_not_apply_default_window_when_another_filter_is_present(): void
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

    /**
     * Regressao do bug relatado: `place_id` presente e vazio (o usuario
     * escolheu "Todos os locais" no select) deve devolver reservas de
     * TODOS os locais do usuario, e nao cair de volta no primeiro place.
     */
    public function test_index_with_explicit_empty_place_id_returns_all_places(): void
    {
        $user = User::factory()->create();
        $placeA = $this->makePlaceWithAdmin($user, 'Casa A');
        $placeB = $this->makePlaceWithAdmin($user, 'Casa B');

        $bookingA = $this->makeBooking($placeA);
        $bookingB = $this->makeBooking($placeB);

        $response = $this->actingAs($user)->get('/app/bookings?place_id=');

        $response->assertInertia(function ($page) use ($bookingA, $bookingB) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($bookingA->id));
            $this->assertTrue($ids->contains($bookingB->id));
        });
    }

    /**
     * Regressao do bug relatado: `status` presente e vazio (o usuario
     * escolheu "Todas") deve incluir reservas concluidas antigas, sem
     * aplicar nenhum recorte de data por status.
     */
    public function test_index_with_explicit_empty_status_includes_old_past_bookings(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $oldPast = $this->makeBooking($place, [
            'check_in' => now()->subDays(30),
            'check_out' => now()->subDays(28),
        ]);

        $response = $this->actingAs($user)->get('/app/bookings?status=&date_from=');

        $response->assertInertia(function ($page) use ($oldPast) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($oldPast->id));
        });
    }

    public function test_index_ignores_place_id_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $booking = $this->makeBooking($place);

        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser, 'Casa Alheia');
        $foreignBooking = $this->makeBooking($foreignPlace);

        $response = $this->actingAs($user)->get("/app/bookings?place_id={$foreignPlace->id}");

        $response->assertInertia(function ($page) use ($booking, $foreignBooking) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($booking->id));
            $this->assertFalse($ids->contains($foreignBooking->id));
        });
    }

    public function test_index_with_invalid_date_from_ignores_the_filter(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $booking = $this->makeBooking($place);

        $response = $this->actingAs($user)->get('/app/bookings?date_from=data-invalida');

        $response->assertInertia(function ($page) use ($booking) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($booking->id));
        });
    }

    public function test_index_with_invalid_status_falls_back_to_all(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $booking = $this->makeBooking($place);

        $response = $this->actingAs($user)->get('/app/bookings?status=bogus&date_from=');

        $response->assertInertia(function ($page) use ($booking) {
            $this->assertSame('all', $page->toArray()['props']['filters']['status']);
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($booking->id));
        });
    }

    /**
     * Ordem exata dos tres grupos: em andamento (check_out asc) -> futuras
     * (check_in asc) -> concluidas (check_out desc). Usa status=all
     * explicito e date_from vazio para nao sofrer com a janela de 7 dias.
     */
    public function test_index_orders_bookings_by_timeline_groups(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $currentSoonerOut = $this->makeBooking($place, [
            'guest_name' => 'Em andamento 1',
            'check_in' => now()->subDays(2),
            'check_out' => now()->addDay(),
        ]);
        $currentLaterOut = $this->makeBooking($place, [
            'guest_name' => 'Em andamento 2',
            'check_in' => now()->subDay(),
            'check_out' => now()->addDays(2),
        ]);
        $futureSoonerIn = $this->makeBooking($place, [
            'guest_name' => 'Futura 1',
            'check_in' => now()->addDays(3),
            'check_out' => now()->addDays(5),
        ]);
        $futureLaterIn = $this->makeBooking($place, [
            'guest_name' => 'Futura 2',
            'check_in' => now()->addDays(4),
            'check_out' => now()->addDays(6),
        ]);
        $pastLaterOut = $this->makeBooking($place, [
            'guest_name' => 'Concluida 1',
            'check_in' => now()->subDays(10),
            'check_out' => now()->subDays(5),
        ]);
        $pastSoonerOut = $this->makeBooking($place, [
            'guest_name' => 'Concluida 2',
            'check_in' => now()->subDays(20),
            'check_out' => now()->subDays(15),
        ]);

        $response = $this->actingAs($user)->get('/app/bookings?status=all&date_from=');

        $response->assertInertia(function ($page) use (
            $currentSoonerOut,
            $currentLaterOut,
            $futureSoonerIn,
            $futureLaterIn,
            $pastLaterOut,
            $pastSoonerOut,
        ) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id')->values()->all();

            $this->assertSame([
                $currentSoonerOut->id,
                $currentLaterOut->id,
                $futureSoonerIn->id,
                $futureLaterIn->id,
                $pastLaterOut->id,
                $pastSoonerOut->id,
            ], $ids);
        });
    }

    /**
     * Semantica de sobreposicao (nao "contido em"): date_from filtra por
     * check_out >= date_from e date_to filtra por check_in <= date_to. Uma
     * reserva que atravessa a borda do intervalo deve aparecer, mesmo que
     * nem check_in nem check_out estejam estritamente "dentro" do range.
     */
    public function test_index_filters_by_date_range_using_overlap_semantics(): void
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

    public function test_index_filters_by_date_range_includes_booking_that_overlaps_the_window_edge(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $overlapping = $this->makeBooking($place, [
            'check_in' => now()->addDay(),
            'check_out' => now()->addDays(15),
        ]);
        $before = $this->makeBooking($place, [
            'check_in' => now()->subDays(20),
            'check_out' => now()->subDays(18),
        ]);

        $dateFrom = now()->addDays(5)->toDateString();
        $dateTo = now()->addDays(10)->toDateString();

        $response = $this->actingAs($user)->get("/app/bookings?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertInertia(function ($page) use ($overlapping, $before) {
            $ids = collect($page->toArray()['props']['bookings']['data'])->pluck('id');
            $this->assertTrue($ids->contains($overlapping->id));
            $this->assertFalse($ids->contains($before->id));
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
