<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Models\Device;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public ?int $placeId = null;

    public function mount(): void
    {
        if ($this->placeId === null) {
            $this->placeId = Auth::user()->placeUsers()->value('place_id');
        }
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $devices = Device::query()
            ->withCount('deviceFunctions')
            ->whereIn('place_id', $userPlaceIds)
            ->when($this->placeId, fn ($query) => $query->where('place_id', $this->placeId))
            ->orderBy('name')
            ->get();

        return view('livewire.devices.index', [
            'places' => $places,
            'devices' => $devices,
        ])->layout('layouts.client');
    }
}
