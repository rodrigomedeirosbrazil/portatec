<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Models\Device;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public ?int $placeId = null;

    public function mount(): void
    {
        $allowedPlaceIds = $this->allowedPlaceIds();

        if (request()->has('place_id')) {
            $requestedId = (int) request()->input('place_id');
            if ($allowedPlaceIds->contains($requestedId)) {
                $this->placeId = $requestedId;
            }
        }

        // Default to "Todos" (null) so the page shows all devices (own + shared)
    }

    public function updatedPlaceId()
    {
        $params = $this->placeId !== null ? ['place_id' => $this->placeId] : [];

        return redirect()->to(route('app.devices.index', $params));
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $allowedPlaceIds = $this->allowedPlaceIds();
        $places = Place::query()
            ->whereIn('id', $allowedPlaceIds->toArray())
            ->orderBy('name')
            ->get();

        $devices = Device::query()
            ->with(['place'])
            ->withCount('deviceFunctions')
            ->where(function ($query) use ($userPlaceIds): void {
                if ($userPlaceIds->isNotEmpty()) {
                    $query->whereIn('place_id', $userPlaceIds);
                }
                $query->orWhereHas('deviceUsers', fn ($q) => $q->where('user_id', Auth::id()));
            })
            ->when($this->placeId, fn ($query) => $query->where('place_id', $this->placeId))
            ->orderBy('name')
            ->get();

        return view('livewire.devices.index', [
            'places' => $places,
            'devices' => $devices,
        ])->layout('layouts.client');
    }

    /**
     * Place IDs the user may filter by: their places + places of devices shared with them.
     *
     * @return Collection<int, int>
     */
    private function allowedPlaceIds(): Collection
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $sharedDevicePlaceIds = Device::query()
            ->whereHas('deviceUsers', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereNotNull('place_id')
            ->pluck('place_id')
            ->unique()
            ->values();

        return $userPlaceIds->merge($sharedDevicePlaceIds)->unique()->filter()->values();
    }
}
