<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\CurrentPlaceService;
use App\Services\DashboardService;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ControlController extends Controller
{
    /**
     * Com um local atual, esta rota é um atalho para o painel daquele local — o
     * mesmo componente de `/app/places/{place}/control`, renderizado pelo mesmo
     * controller. Sem local atual, lista os locais para escolher.
     */
    public function index(
        CurrentPlaceService $currentPlace,
        PlaceControlController $placeControl,
        TuyaIntegrationService $tuya,
        DashboardService $dashboard,
    ): Response {
        $place = $currentPlace->get(Auth::user());

        if ($place !== null) {
            return $placeControl->show($place, $tuya);
        }

        $data = $dashboard->forUser(Auth::user());

        return Inertia::render('control/index', [
            'places' => $data['places']->map(fn (Place $place): array => [
                'id' => $place->id,
                'name' => $place->name,
                'devices_count' => $place->devices_count,
                'online_count' => $data['onlineCountByPlace']->get($place->id, 0),
            ])->values(),
        ]);
    }
}
