<?php

declare(strict_types=1);

namespace App\Livewire\Bookings;

use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load('accessCode');

        abort_unless(
            Auth::user()->placeUsers()->where('place_id', $this->booking->place_id)->exists(),
            403
        );
    }

    public function render(): View
    {
        return view('livewire.bookings.show')->layout('layouts.client');
    }
}
