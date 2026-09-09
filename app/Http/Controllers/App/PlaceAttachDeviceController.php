<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceAttachDeviceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Device;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlaceAttachDeviceController extends Controller
{
    /**
     * Spec §5. A lista é "o que EU posso trazer para cá": dispositivos que eu
     * administro mais os que me foram concedidos. A versão anterior tinha um
     * ramo `whereDoesntHave('places')->whereNull('place_id')` sem escopo
     * nenhum, que mostrava os dispositivos sem local de TODAS as contas.
     */
    public function create(Place $place): Response
    {
        $this->authorize('update', $place);

        $devices = Device::query()
            ->withCount('deviceFunctions')
            ->with('places')
            ->whereHas('deviceUsers', fn (Builder $query) => $query->where('user_id', Auth::id()))
            ->whereDoesntHave('places', fn (Builder $query) => $query->where('places.id', $place->id))
            ->where(fn (Builder $query) => $query->whereNull('place_id')->orWhere('place_id', '!=', $place->id))
            ->orderBy('name')
            ->get();

        return Inertia::render('places/attach-device', [
            'place' => new PlaceResource($place),
            'devices' => $devices->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'brand' => $device->brand?->value,
                'device_functions_count' => $device->device_functions_count,
                'place_names' => $device->places->pluck('name')->values(),
                'fallback_place_name' => $device->place?->name,
            ])->values(),
        ]);
    }

    public function store(StorePlaceAttachDeviceRequest $request, Place $place): RedirectResponse
    {
        $this->authorize('update', $place);

        $device = Device::query()->findOrFail($request->validated()['deviceId']);

        // A checagem que faltava: ser admin do local de destino não diz nada
        // sobre o direito de mexer NESTE dispositivo.
        $this->authorize('attach', $device);

        if ($device->places()->where('places.id', $place->id)->exists() || $device->place_id === $place->id) {
            return redirect()
                ->route('app.places.show', ['place' => $place->id])
                ->with('status', __('app.device_already_in_place'));
        }

        $device->places()->syncWithoutDetaching([$place->id]);

        if ($device->place_id === null) {
            $device->update(['place_id' => $place->id]);
        }

        return redirect()
            ->route('app.places.show', ['place' => $place->id])
            ->with('status', __('app.device_attached', ['name' => $device->name]));
    }
}
