<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateIntegrationPlaceRequest;
use App\Models\Integration;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * O `external_id` (URL/identificador iCal) de um place dentro de uma
 * integração — o pivot de `Integration::places()`.
 */
class IntegrationPlaceController extends Controller
{
    /**
     * Porte 1:1 de `App\Livewire\Integrations\Edit::updateExternalId()`.
     * Dois models de route-model-binding (`Integration` e `Place`): ambos
     * vêm crus da URL, então ambos precisam de autorização própria aqui.
     */
    public function update(UpdateIntegrationPlaceRequest $request, Integration $integration, Place $place): RedirectResponse
    {
        abort_unless(Auth::user()?->can('update', $integration), 403);
        abort_unless(Auth::user()?->can('view', $place), 403);
        abort_unless($integration->places()->where('places.id', $place->id)->exists(), 404);

        $validated = $request->validated();

        $integration->places()->updateExistingPivot($place->id, [
            'external_id' => $validated['externalId'],
        ]);

        $integration->touch();

        return redirect()
            ->route('app.bookings.integrations.edit', ['integration' => $integration->id])
            ->with('status', __('app.integration_updated'));
    }

    /**
     * Porte 1:1 de `App\Livewire\Integrations\Edit::removePlace()`: se a
     * integração ficar sem nenhum place após o detach, ela é apagada por
     * inteiro e o usuário volta para a listagem — regra fácil de perder na
     * reescrita.
     */
    public function destroy(Request $request, Integration $integration, Place $place): RedirectResponse
    {
        abort_unless(Auth::user()?->can('update', $integration), 403);
        abort_unless(Auth::user()?->can('view', $place), 403);

        $integration->places()->detach($place->id);
        $integration->refresh();

        if ($integration->places()->doesntExist()) {
            $integration->delete();

            return redirect()
                ->route('app.bookings.integrations.index')
                ->with('status', __('app.integration_deleted'));
        }

        return redirect()
            ->route('app.bookings.integrations.edit', ['integration' => $integration->id])
            ->with('status', __('app.integration_place_removed'));
    }
}
