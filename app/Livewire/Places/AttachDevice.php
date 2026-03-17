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

        if ($device->places()->where('places.id', $this->place->id)->exists()) {
            session()->flash('status', 'Este dispositivo já está associado a este local.');

            return $this->redirectRoute('app.places.show', ['place' => $this->place->id], navigate: true);
        }

        $device->places()->syncWithoutDetaching([$this->place->id]);
        if ($device->place_id === null) {
            $device->update(['place_id' => $this->place->id]);
        }

        $functionIds = $device->deviceFunctions->pluck('id');

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
            ->with('places')
            ->where(function ($query) use ($userPlaceIds): void {
                $query->whereDoesntHave('places')
                    ->orWhereHas('places', fn ($q) => $q->whereIn('places.id', $userPlaceIds));
            })
            ->whereDoesntHave('places', fn ($query) => $query->where('places.id', $this->place->id))
            ->orderBy('name')
            ->get();

        return view('livewire.places.attach-device', [
            'devices' => $devices,
        ])->layout('layouts.client');
    }
}
