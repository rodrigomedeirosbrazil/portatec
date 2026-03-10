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

    public bool $canDelete = false;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load('accessCode');

        abort_unless(
            Auth::user()->placeUsers()->where('place_id', $this->booking->place_id)->exists(),
            403
        );

        $this->canDelete = $this->booking->source === 'manual';
    }

    public function deleteBooking(): void
    {
        if (! $this->canDelete) {
            abort(403);
        }

        $this->booking->delete();

        session()->flash('status', 'Reserva removida com sucesso.');
        $this->redirectRoute('app.bookings.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.bookings.show')->layout('layouts.client');
    }
}
