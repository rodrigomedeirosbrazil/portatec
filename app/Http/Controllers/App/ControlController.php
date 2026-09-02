<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ControlController extends Controller
{
    /**
     * Lista os locais para escolher qual controlar. **Sempre** a lista — esta
     * rota já foi polimórfica (painel do local atual quando havia um, lista
     * quando não), e isso se mostrou errado: uma URL com dois significados não
     * tem lugar fixo na hierarquia, então o breadcrumb do painel apontava para
     * `/app/control`, que renderizava o próprio painel. Link de pai para a
     * própria página.
     *
     * O atalho de um clique não sumiu: ele vive no `href` do item "Controle" da
     * sidebar, que aponta direto para `/app/places/{atual}/control` quando há
     * local atual. A esperteza fica na navegação, não na rota.
     */
    public function index(DashboardService $dashboard): Response
    {
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
