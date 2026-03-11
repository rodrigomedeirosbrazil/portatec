<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AttachDevice extends Component
{
    public Place $place;

    public ?int $deviceId = null;

    public function mount(Place $place): void
    {
        $this->place = $place;

        abort_unless(
            $this->place->placeUsers()->where('user_id', Auth::id())->exists(),
            403
        );
    }

    public function attach(): mixed
    {
        $this->authorize('update', $this->place);

        $validated = $this->validate([
            'deviceId' => ['required', 'integer', 'exists:devices,id'],
        ]);

        $device = Device::query()
            ->with('deviceFunctions')
            ->findOrFail($validated['deviceId']);

        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $canAttach = $device->place_id === null
            || $userPlaceIds->contains($device->place_id);

        abort_unless($canAttach, 403);

        if ($device->place_id === $this->place->id) {
            session()->flash('status', 'Este dispositivo já está associado a este local.');

            return $this->redirectRoute('app.places.show', ['place' => $this->place->id], navigate: true);
        }

        $device->update(['place_id' => $this->place->id]);

        $functionIds = $device->deviceFunctions->pluck('id');

        PlaceDeviceFunction::query()
            ->whereIn('device_function_id', $functionIds)
            ->where('place_id', '!=', $this->place->id)
            ->delete();

        foreach ($functionIds as $deviceFunctionId) {
            PlaceDeviceFunction::firstOrCreate(
                [
                    'place_id' => $this->place->id,
                    'device_function_id' => $deviceFunctionId,
                ]
            );
        }

        session()->flash('status', "Dispositivo \"{$device->name}\" associado ao local com sucesso.");

        return $this->redirectRoute('app.places.show', ['place' => $this->place->id], navigate: true);
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $devices = Device::query()
            ->withCount('deviceFunctions')
            ->with('place')
            ->where(function ($query) use ($userPlaceIds): void {
                $query->whereNull('place_id')
                    ->orWhereIn('place_id', $userPlaceIds);
            })
            ->where(function ($query): void {
                $query->whereNull('place_id')
                    ->orWhere('place_id', '!=', $this->place->id);
            })
            ->orderBy('name')
            ->get();

        return view('livewire.places.attach-device', [
            'devices' => $devices,
        ])->layout('layouts.client');
    }
}
