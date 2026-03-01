<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        $placeIds = $user->placeUsers()->pluck('place_id');

        $places = Place::query()
            ->whereIn('id', $placeIds)
            ->with('devices')
            ->withCount('devices')
            ->orderBy('name')
            ->get();

        $nextCheckInByPlace = Booking::query()
            ->whereIn('place_id', $placeIds)
            ->where('check_in', '>=', now())
            ->orderBy('check_in')
            ->get()
            ->groupBy('place_id')
            ->map(fn ($bookings) => $bookings->first());

        $onlineCountByPlace = $places->mapWithKeys(function (Place $place) {
            $onlineCount = $place->devices->filter(fn ($device) => $device->isAvailable())->count();

            return [$place->id => $onlineCount];
        });

        return view('livewire.dashboard', [
            'places' => $places,
            'nextCheckInByPlace' => $nextCheckInByPlace,
            'onlineCountByPlace' => $onlineCountByPlace,
        ])->layout('layouts.client');
    }
}
