<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationResource;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DeviceIntegrationController extends Controller
{
    /**
     * Skeleton — see `App\Livewire\Devices\Integrations\Index`.
     */
    public function index(Request $request): Response
    {
        $integrations = Integration::query()
            ->where('user_id', Auth::id())
            ->whereHas('platform', fn ($query) => $query->where('slug', 'tuya'))
            ->with('platform')
            ->latest('updated_at')
            ->get();

        return Inertia::render('devices/integrations/index', [
            'integrations' => IntegrationResource::collection($integrations),
        ]);
    }
}
