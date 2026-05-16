<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AccessCode;
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
        $now = now();

        $places = Place::query()
            ->whereIn('id', $placeIds)
            ->with('devices')
            ->withCount('devices')
            ->orderBy('name')
            ->get();

        $nextCheckInByPlace = Booking::query()
            ->whereIn('place_id', $placeIds)
            ->where('check_in', '>=', $now)
            ->orderBy('check_in')
            ->get()
            ->groupBy('place_id')
            ->map(fn ($bookings) => $bookings->first());

        $onlineCountByPlace = $places->mapWithKeys(function (Place $place) {
            $onlineCount = $place->devices->filter(fn ($device) => $device->isAvailable())->count();

            return [$place->id => $onlineCount];
        });

        $totalDevices = $places->sum('devices_count');
        $totalOnline = $onlineCountByPlace->sum();
        $totalOffline = $totalDevices - $totalOnline;

        $activeBookings = Booking::query()
            ->whereIn('place_id', $placeIds)
            ->where('check_in', '<=', $now)
            ->where('check_out', '>=', $now)
            ->count();

        $todayCheckIns = Booking::query()
            ->whereIn('place_id', $placeIds)
            ->whereDate('check_in', $now->toDateString())
            ->where('check_in', '>=', $now)
            ->count();

        $activeAccessCodes = AccessCode::query()
            ->whereIn('place_id', $placeIds)
            ->where('start', '<=', $now)
            ->where(fn ($q) => $q->whereNull('end')->orWhere('end', '>=', $now))
            ->count();

        return view('livewire.dashboard', [
            'places' => $places,
            'nextCheckInByPlace' => $nextCheckInByPlace,
            'onlineCountByPlace' => $onlineCountByPlace,
            'totalDevices' => $totalDevices,
            'totalOnline' => $totalOnline,
            'totalOffline' => $totalOffline,
            'activeBookings' => $activeBookings,
            'todayCheckIns' => $todayCheckIns,
            'activeAccessCodes' => $activeAccessCodes,
        ])->layout('layouts.client');
    }
}
