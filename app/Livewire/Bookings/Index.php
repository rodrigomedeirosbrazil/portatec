<?php

declare(strict_types=1);

namespace App\Livewire\Bookings;

use App\Models\Booking;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public ?int $placeId = null;

    public function mount(): void
    {
        if ($this->placeId === null) {
            $this->placeId = Auth::user()->placeUsers()->value('place_id');
        }
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $bookings = Booking::query()
            ->whereIn('place_id', $userPlaceIds)
            ->when($this->placeId, fn ($query) => $query->where('place_id', $this->placeId))
            ->orderBy('check_in')
            ->limit(50)
            ->get();

        return view('livewire.bookings.index', [
            'places' => $places,
            'bookings' => $bookings,
        ])->layout('layouts.client');
    }
}
