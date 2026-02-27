<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Booking;
use App\Services\AccessCode\AccessCodeGeneratorService;

class BookingObserver
{
    public function __construct(
        private AccessCodeGeneratorService $generator
    ) {}

    public function created(Booking $booking): void
    {
        $this->generator->createForBooking($booking);
    }

    public function updated(Booking $booking): void
    {
        $accessCode = $booking->accessCode;

        if ($accessCode) {
            $accessCode->update([
                'label' => $booking->guest_name ?: $accessCode->label,
                'start' => $booking->check_in,
                'end' => $booking->check_out,
            ]);
        } else {
            $this->generator->createForBooking($booking);
        }
    }

    public function deleted(Booking $booking): void
    {
        $booking->accessCode?->delete();
    }

    public function restored(Booking $booking): void
    {
        if (!$booking->accessCode) {
            $this->generator->createForBooking($booking);
        }
    }

    public function forceDeleted(Booking $booking): void
    {
        $booking->accessCode?->forceDelete();
    }
}
