<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public Place $place;

    public function mount(Place $place): void
    {
        $this->place = $place->load([
            'devices',
            'bookings' => fn ($q) => $q->latest('check_in')->limit(10),
            'accessCodes',
            'placeUsers.user',
        ]);

        abort_unless(
            $this->place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );
    }

    public function removeDevice(int $deviceId): void
    {
        $this->authorize('update', $this->place);

        $device = Device::query()
            ->where('id', $deviceId)
            ->whereHas('places', fn ($query) => $query->where('places.id', $this->place->id))
            ->firstOrFail();

        $deviceFunctionIds = $device->deviceFunctions()->pluck('id');

        PlaceDeviceFunction::query()
            ->where('place_id', $this->place->id)
            ->whereIn('device_function_id', $deviceFunctionIds)
            ->delete();

        $this->place->devices()->detach($device->id);

        $device->load('places');
        $device->update(['place_id' => $device->places->first()?->id]);

        $this->place->load('devices');
        $this->dispatch('device-removed');
    }

    public function render(): View
    {
        $activeAccessCodes = $this->place->accessCodes()
            ->where('start', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end')->orWhere('end', '>=', now());
            })
            ->count();

        return view('livewire.places.show', [
            'activeAccessCodes' => $activeAccessCodes,
        ])->layout('layouts.client');
    }
}
