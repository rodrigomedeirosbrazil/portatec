<?php

declare(strict_types=1);

namespace App\Livewire\Bookings;

use App\Models\Booking;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    public ?int $placeId = null;

    public ?string $guestName = null;

    public string $checkIn = '';

    public string $checkOut = '';

    public function mount(): void
    {
        if ($this->placeId === null) {
            $this->placeId = Auth::user()->placeUsers()->value('place_id');
        }
    }

    protected function rules(): array
    {
        return [
            'placeId' => ['required', 'integer', 'exists:places,id'],
            'guestName' => ['nullable', 'string', 'max:255'],
            'checkIn' => ['required', 'date'],
            'checkOut' => ['required', 'date', 'after:checkIn'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        $hasAccess = Auth::user()
            ->placeUsers()
            ->where('place_id', $validated['placeId'])
            ->exists();

        abort_unless($hasAccess, 403);

        $booking = Booking::create([
            'place_id' => $validated['placeId'],
            'guest_name' => $validated['guestName'],
            'check_in' => $validated['checkIn'],
            'check_out' => $validated['checkOut'],
            'source' => 'manual',
        ]);

        session()->flash('status', 'Booking criado com sucesso.');

        return $this->redirectRoute('app.bookings.show', ['booking' => $booking->id], navigate: true);
    }

    public function render(): View
    {
        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        return view('livewire.bookings.create', ['places' => $places])
            ->layout('layouts.client');
    }
}
