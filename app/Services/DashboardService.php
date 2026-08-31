<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AccessCode;
use App\Models\Booking;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Ported 1:1 from `App\Livewire\Dashboard::render()`: every aggregation
     * is scoped to the places the given user belongs to (`placeUsers`).
     *
     * @return array{
     *     places: Collection<int, Place>,
     *     nextCheckInByPlace: Collection<int, Booking>,
     *     onlineCountByPlace: Collection<int, int>,
     *     totalDevices: int,
     *     totalOnline: int,
     *     totalOffline: int,
     *     activeBookings: int,
     *     todayCheckIns: int,
     *     activeAccessCodes: int,
     * }
     */
    public function forUser(User $user): array
    {
        $placeIds = $user->placeUsers()->pluck('place_id');
        $now = Carbon::now();

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
            ->map(fn (Collection $bookings) => $bookings->first());

        $onlineCountByPlace = $places->mapWithKeys(function (Place $place) {
            $onlineCount = $place->devices->filter(fn ($device) => $device->isAvailable())->count();

            return [$place->id => $onlineCount];
        });

        $totalDevices = (int) $places->sum('devices_count');
        $totalOnline = (int) $onlineCountByPlace->sum();
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

        return [
            'places' => $places,
            'nextCheckInByPlace' => $nextCheckInByPlace,
            'onlineCountByPlace' => $onlineCountByPlace,
            'totalDevices' => $totalDevices,
            'totalOnline' => $totalOnline,
            'totalOffline' => $totalOffline,
            'activeBookings' => $activeBookings,
            'todayCheckIns' => $todayCheckIns,
            'activeAccessCodes' => $activeAccessCodes,
        ];
    }
}
