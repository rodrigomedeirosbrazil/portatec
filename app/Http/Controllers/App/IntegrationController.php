<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIntegrationRequest;
use App\Http\Resources\IntegrationResource;
use App\Http\Resources\PlaceResource;
use App\Http\Resources\PlatformResource;
use App\Jobs\SyncIntegrationBookingsJob;
use App\Models\Integration;
use App\Models\Place;
use App\Models\Platform;
use App\Services\CurrentPlaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Integrações iCal (Airbnb e afins). NÃO cobre `Integrations/TuyaConnect` —
 * já migrado na fase 4 como `App\Http\Controllers\App\TuyaConnectController`.
 * As duas convivem na tabela `integrations`, distinguidas por
 * `platform.slug !== 'tuya'`.
 */
class IntegrationController extends Controller
{
    /**
     * Porte 1:1 de `App\Livewire\Integrations\Index::render()`, incluindo o
     * filtro `platform.slug != 'tuya'` e o filtro opcional por place.
     */
    public function index(Request $request, CurrentPlaceService $currentPlace): Response
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $placeFilter = $currentPlace->resolveForRequest($request, Auth::user());

        $integrations = Integration::query()
            ->where('user_id', Auth::id())
            ->whereHas('platform', fn (Builder $query) => $query->where('slug', '!=', 'tuya'))
            ->with(['platform', 'places'])
            ->when(
                $placeFilter !== null,
                fn (Builder $query) => $query->whereHas('places', fn ($q) => $q->where('places.id', $placeFilter))
            )
            ->latest('updated_at')
            ->get();

        return Inertia::render('integrations/index', [
            'integrations' => IntegrationResource::collection($integrations),
            'places' => PlaceResource::collection($places),
            'placeId' => $placeFilter !== null ? (string) $placeFilter : null,
        ]);
    }

    /**
     * Porte 1:1 de `App\Livewire\Integrations\Create::render()` / `mount()`:
     * plataformas com slug != tuya, ordenadas por nome, com a primeira já
     * selecionada; places do usuário, com o primeiro como padrão.
     */
    public function create(Request $request): Response
    {
        $platforms = Platform::query()
            ->where('slug', '!=', 'tuya')
            ->orderBy('name')
            ->get();

        $places = Place::query()
            ->whereHas('placeUsers', fn (Builder $query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        $requestedPlaceId = $request->integer('place_id');
        $selectedPlaceId = $places->contains('id', $requestedPlaceId)
            ? $requestedPlaceId
            : $places->first()?->id;

        return Inertia::render('integrations/create', [
            'platforms' => PlatformResource::collection($platforms),
            'places' => PlaceResource::collection($places),
            'platformId' => $platforms->first()?->id,
            'placeId' => $selectedPlaceId,
        ]);
    }

    /**
     * Porte 1:1 de `App\Livewire\Integrations\Create::save()`.
     */
    public function store(StoreIntegrationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $hasAccess = Auth::user()
            ->placeUsers()
            ->where('place_id', $validated['placeId'])
            ->exists();

        abort_unless($hasAccess, 403);

        $integration = Integration::firstOrCreate([
            'platform_id' => $validated['platformId'],
            'user_id' => Auth::id(),
        ]);

        $integration->places()->syncWithoutDetaching([
            $validated['placeId'] => ['external_id' => $validated['externalId']],
        ]);

        SyncIntegrationBookingsJob::dispatch($integration->id, $validated['placeId']);

        return redirect()
            ->route('app.bookings.integrations.index')
            ->with('status', __('app.integration_created'));
    }

    /**
     * `{integration}` vem cru da URL via route-model-binding: sem o
     * snapshot assinado que o Livewire usava, a autorização precisa
     * acontecer aqui, logo no início.
     */
    public function edit(Request $request, Integration $integration): Response
    {
        abort_unless(Auth::user()?->can('update', $integration), 403);
        abort_if($integration->platform?->slug === 'tuya', 404);

        return Inertia::render('integrations/edit', [
            'integration' => new IntegrationResource($integration->load(['platform', 'places'])),
        ]);
    }

    /**
     * Porte 1:1 de `App\Livewire\Integrations\Index::deleteIntegration()` /
     * `App\Livewire\Integrations\Edit::deleteIntegration()`: desassocia
     * todos os places e apaga a integração.
     */
    public function destroy(Request $request, Integration $integration): RedirectResponse
    {
        abort_unless(Auth::user()?->can('delete', $integration), 403);

        $integration->places()->detach();
        $integration->delete();

        return redirect()
            ->route('app.bookings.integrations.index')
            ->with('status', __('app.integration_deleted'));
    }
}
