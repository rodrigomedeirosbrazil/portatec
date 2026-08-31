<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTimelineOrderTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * scopeOrderByTimeline deve produzir a ordem dos tres grupos definida
     * na decisao 3 do spec: em andamento (check_out asc) -> futuras
     * (check_in asc) -> concluidas (check_out desc).
     */
    public function test_order_by_timeline_groups_current_future_and_past_in_order(): void
    {
        $place = Place::create(['name' => 'Casa Teste']);
        $now = now();

        $currentSoonerOut = $this->makeBooking($place, [
            'check_in' => $now->copy()->subDays(2),
            'check_out' => $now->copy()->addDay(),
        ]);
        $currentLaterOut = $this->makeBooking($place, [
            'check_in' => $now->copy()->subDay(),
            'check_out' => $now->copy()->addDays(2),
        ]);
        $futureSoonerIn = $this->makeBooking($place, [
            'check_in' => $now->copy()->addDays(3),
            'check_out' => $now->copy()->addDays(5),
        ]);
        $futureLaterIn = $this->makeBooking($place, [
            'check_in' => $now->copy()->addDays(4),
            'check_out' => $now->copy()->addDays(6),
        ]);
        $pastLaterOut = $this->makeBooking($place, [
            'check_in' => $now->copy()->subDays(10),
            'check_out' => $now->copy()->subDays(5),
        ]);
        $pastSoonerOut = $this->makeBooking($place, [
            'check_in' => $now->copy()->subDays(20),
            'check_out' => $now->copy()->subDays(15),
        ]);

        $ids = Booking::query()
            ->orderByTimeline($now)
            ->pluck('id')
            ->all();

        $this->assertSame([
            $currentSoonerOut->id,
            $currentLaterOut->id,
            $futureSoonerIn->id,
            $futureLaterIn->id,
            $pastLaterOut->id,
            $pastSoonerOut->id,
        ], $ids);
    }

    /**
     * Tie-breaker por id: duas reservas com check_in e check_out
     * identicos (mesmo grupo e mesmo criterio de ordenacao dentro do
     * grupo) devem sair em ordem crescente de id, garantindo paginacao
     * estavel.
     */
    public function test_order_by_timeline_breaks_ties_by_id_ascending(): void
    {
        $place = Place::create(['name' => 'Casa Teste']);
        $now = now();

        $checkIn = $now->copy()->addDays(3);
        $checkOut = $now->copy()->addDays(5);

        $first = $this->makeBooking($place, ['check_in' => $checkIn, 'check_out' => $checkOut]);
        $second = $this->makeBooking($place, ['check_in' => $checkIn, 'check_out' => $checkOut]);

        $ids = Booking::query()
            ->orderByTimeline($now)
            ->pluck('id')
            ->all();

        $this->assertSame([$first->id, $second->id], $ids);
        $this->assertLessThan($second->id, $first->id);
    }
}
