<?php

declare(strict_types=1);

namespace App\Services\AccessCode;

use App\Models\AccessCode;
use App\Models\Booking;
use Carbon\CarbonInterface;

class AccessCodeGeneratorService
{
    public function __construct(
        private AccessCodeConflictChecker $conflictChecker
    ) {}

    public function createForBooking(Booking $booking): AccessCode
    {
        return AccessCode::create([
            'place_id' => $booking->place_id,
            'booking_id' => $booking->id,
            'user_id' => $booking->integration?->user_id,
            'pin' => $this->generatePin($booking->place_id, $booking->check_in, $booking->check_out),
            'start' => $booking->check_in,
            'end' => $booking->check_out,
        ]);
    }

    public function createStandalone(
        int $placeId,
        ?int $userId,
        CarbonInterface $start,
        ?CarbonInterface $end,
        ?string $pin = null
    ): AccessCode {
        return AccessCode::create([
            'place_id' => $placeId,
            'user_id' => $userId,
            'booking_id' => null,
            'pin' => $pin ?: $this->generatePin($placeId, $start, $end),
            'start' => $start,
            'end' => $end,
        ]);
    }

    /**
     * Spec §7: sorteia até achar um PIN que não colida, na janela pedida, em
     * nenhum dispositivo alcançado por este local. O laço é seguro — são
     * 10^6 combinações contra um punhado de códigos ativos por equipamento.
     */
    public function generatePin(int $placeId, CarbonInterface $start, ?CarbonInterface $end): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while ($this->conflictChecker->conflicts($placeId, $pin, $start, $end));

        return $pin;
    }
}
