<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Models\Device;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?int $placeId = null;

    public string $search = '';

    private const PER_PAGE = 20;

    public function mount(): void
    {
        $allowedPlaceIds = $this->allowedPlaceIds();

        if (request()->has('place_id')) {
            $requestedId = (int) request()->input('place_id');
            if ($allowedPlaceIds->contains($requestedId)) {
                $this->placeId = $requestedId;
            }
        }
    }

    public function updatedPlaceId()
    {
        $params = $this->placeId !== null ? ['place_id' => $this->placeId] : [];

        return redirect()->to(route('app.devices.index', $params));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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
            ->when($this->search !== '', function ($query): void {
                $term = '%'.addcslashes($this->search, '%_').'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE);

        return view('livewire.devices.index', [
            'places' => $places,
            'devices' => $devices,
        ])->layout('layouts.client');
    }

    /**
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
