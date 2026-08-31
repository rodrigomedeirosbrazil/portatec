<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceAttachDeviceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlaceAttachDeviceController extends Controller
{
    public function create(Place $place): Response
    {
        abort_unless(
            $place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );

        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $devices = Device::query()
            ->withCount('deviceFunctions')
            ->with('places')
            ->where(function ($query) use ($userPlaceIds): void {
                $query->where(function ($query): void {
                    $query->whereDoesntHave('places')
                        ->whereNull('place_id');
                })
                    ->orWhereHas('places', fn ($q) => $q->whereIn('places.id', $userPlaceIds))
                    ->orWhereIn('place_id', $userPlaceIds);
            })
            ->where(function ($query) use ($place): void {
                $query->whereDoesntHave('places', fn ($query) => $query->where('places.id', $place->id))
                    ->where(function ($query) use ($place): void {
                        $query->whereNull('place_id')
                            ->orWhere('place_id', '!=', $place->id);
                    });
            })
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

        $validated = $request->validated();

        $device = Device::query()
            ->with('deviceFunctions')
            ->findOrFail($validated['deviceId']);

        if ($device->places()->where('places.id', $place->id)->exists() || $device->place_id === $place->id) {
            return redirect()
                ->route('app.places.show', ['place' => $place->id])
                ->with('status', __('app.device_already_in_place'));
        }

        $device->places()->syncWithoutDetaching([$place->id]);
        if ($device->place_id === null) {
            $device->update(['place_id' => $place->id]);
        }

        $functionIds = $device->deviceFunctions->pluck('id');

        foreach ($functionIds as $deviceFunctionId) {
            PlaceDeviceFunction::firstOrCreate([
                'place_id' => $place->id,
                'device_function_id' => $deviceFunctionId,
            ]);
        }

        return redirect()
            ->route('app.places.show', ['place' => $place->id])
            ->with('status', __('app.device_attached', ['name' => $device->name]));
    }
}
