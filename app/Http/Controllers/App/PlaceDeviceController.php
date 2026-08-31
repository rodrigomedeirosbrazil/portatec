<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlaceDeviceController extends Controller
{
    public function destroy(Request $request, Place $place, Device $device): RedirectResponse
    {
        Gate::authorize('update', $place);

        $device = Device::query()
            ->where('id', $device->id)
            ->where(function ($query) use ($place): void {
                $query->whereHas('places', fn ($query) => $query->where('places.id', $place->id))
                    ->orWhere('place_id', $place->id);
            })
            ->firstOrFail();

        $deviceFunctionIds = $device->deviceFunctions()->pluck('id');

        PlaceDeviceFunction::query()
            ->where('place_id', $place->id)
            ->whereIn('device_function_id', $deviceFunctionIds)
            ->delete();

        $place->devices()->detach($device->id);

        $device->load('places');
        $device->update(['place_id' => $device->places->first()?->id]);

        return redirect()->route('app.places.show', ['place' => $place->id]);
    }
}
