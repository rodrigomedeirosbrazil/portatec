<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Place;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Ported 1:1 from `App\Livewire\Dashboard::render()`. All aggregations
     * live in `DashboardService`, scoped to the authenticated user's
     * places; this controller only fetches and shapes the response.
     */
    public function index(Request $request, DashboardService $dashboardService): Response
    {
        $data = $dashboardService->forUser(Auth::user());

        $places = $data['places']->map(function (Place $place) use ($data) {
            /** @var Booking|null $nextCheckIn */
            $nextCheckIn = $data['nextCheckInByPlace']->get($place->id);

            return [
                'id' => $place->id,
                'name' => $place->name,
                'devices_count' => $place->devices_count,
                'online_count' => $data['onlineCountByPlace']->get($place->id, 0),
                'next_check_in' => $nextCheckIn?->check_in?->toIso8601String(),
            ];
        })->values();

        return Inertia::render('dashboard', [
            'places' => $places,
            'totalDevices' => $data['totalDevices'],
            'totalOnline' => $data['totalOnline'],
            'totalOffline' => $data['totalOffline'],
            'activeBookings' => $data['activeBookings'],
            'todayCheckIns' => $data['todayCheckIns'],
            'activeAccessCodes' => $data['activeAccessCodes'],
        ]);
    }
}
