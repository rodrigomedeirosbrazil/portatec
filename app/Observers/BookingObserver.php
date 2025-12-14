<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AccessCode;
use App\Models\Booking;

class BookingObserver
{
    /**
     * Handle the Booking "created" event.
     */
    public function created(Booking $booking): void
    {
        // Criar AccessCode para todos os bookings
        $pin = $this->generateUniquePin($booking->place_id);

        AccessCode::create([
            'place_id' => $booking->place_id,
            'booking_id' => $booking->id,
            'pin' => $pin,
            'start' => $booking->check_in,
            'end' => $booking->check_out,
            'user_id' => $booking->integration?->user_id,
        ]);
    }

    /**
     * Handle the Booking "updated" event.
     */
    public function updated(Booking $booking): void
    {
        // Atualizar AccessCode se check_in/check_out mudarem
        $accessCode = $booking->accessCode;

        if ($accessCode) {
            $accessCode->update([
                'start' => $booking->check_in,
                'end' => $booking->check_out,
            ]);
        } else {
            // Se AccessCode não existir, criar
            $pin = $this->generateUniquePin($booking->place_id);

            AccessCode::create([
                'place_id' => $booking->place_id,
                'booking_id' => $booking->id,
                'pin' => $pin,
                'start' => $booking->check_in,
                'end' => $booking->check_out,
                'user_id' => $booking->integration?->user_id,
            ]);
        }
    }

    /**
     * Handle the Booking "deleted" event.
     */
    public function deleted(Booking $booking): void
    {
        // Deletar AccessCode associado
        $booking->accessCode?->delete();
    }

    /**
     * Handle the Booking "restored" event.
     */
    public function restored(Booking $booking): void
    {
        // Quando restaurado, criar AccessCode novamente
        if (!$booking->accessCode) {
            $pin = $this->generateUniquePin($booking->place_id);

            AccessCode::create([
                'place_id' => $booking->place_id,
                'booking_id' => $booking->id,
                'pin' => $pin,
                'start' => $booking->check_in,
                'end' => $booking->check_out,
                'user_id' => $booking->integration?->user_id,
            ]);
        }
    }

    /**
     * Handle the Booking "force deleted" event.
     */
    public function forceDeleted(Booking $booking): void
    {
        // Force delete do AccessCode também
        $booking->accessCode?->forceDelete();
    }

    /**
     * Generate a unique PIN for a place
     */
    private function generateUniquePin(int $placeId): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (AccessCode::where('place_id', $placeId)
            ->where('pin', $pin)
            ->where(function ($query) {
                $query->where('start', '<=', now())
                      ->where('end', '>=', now());
            })
            ->exists());

        return $pin;
    }
}
