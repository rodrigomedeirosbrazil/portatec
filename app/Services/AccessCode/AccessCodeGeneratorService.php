<?php

declare(strict_types=1);

namespace App\Services\AccessCode;

use App\Models\AccessCode;
use App\Models\Booking;
use Carbon\CarbonInterface;

class AccessCodeGeneratorService
{
    public function createForBooking(Booking $booking): AccessCode
    {
        return AccessCode::create([
            'place_id' => $booking->place_id,
            'booking_id' => $booking->id,
            'user_id' => $booking->integration?->user_id,
            'pin' => $this->generatePin($booking->place_id),
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
            'pin' => $pin ?: $this->generatePin($placeId),
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function generatePin(int $placeId): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (AccessCode::where('place_id', $placeId)->where('pin', $pin)->exists());

        return $pin;
    }
}
