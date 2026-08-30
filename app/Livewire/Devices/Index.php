<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Models\Device;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?string $placeId = null;

    public string $search = '';

    private const PER_PAGE = 20;

    public function mount(): void
    {
        $allowedPlaceIds = $this->allowedPlaceIds();

        if (request()->has('place_id')) {
            $requested = (string) request()->input('place_id');
            if ($requested === 'unassigned') {
                $this->placeId = 'unassigned';
            } else {
                $requestedId = (int) $requested;
                if ($allowedPlaceIds->contains($requestedId)) {
                    $this->placeId = (string) $requestedId;
                }
            }
        }
    }

    public function updatedPlaceId()
    {
        $placeId = $this->placeId;
        if ($placeId === '') {
            $placeId = null;
        }

        if ($placeId === 'unassigned') {
            $params = ['place_id' => 'unassigned'];
        } elseif ($placeId !== null) {
            $params = ['place_id' => (int) $placeId];
        } else {
            $params = [];
        }

        return redirect()->to(route('app.devices.index', $params));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');
        $hasDeviceUserTable = Schema::hasTable('device_user');

        $allowedPlaceIds = $this->allowedPlaceIds();
        $places = Place::query()
            ->whereIn('id', $allowedPlaceIds->toArray())
            ->orderBy('name')
            ->get();

        $placeFilter = $this->placeId === '' ? null : $this->placeId;

        $devices = Device::query()
            ->with(['places', 'place'])
            ->withCount('deviceFunctions')
            ->where(function ($query) use ($userPlaceIds, $hasDeviceUserTable): void {
                if ($userPlaceIds->isNotEmpty()) {
                    $query->where(function ($query) use ($userPlaceIds): void {
                        $query->whereHas('places', fn ($q) => $q->whereIn('places.id', $userPlaceIds))
                            ->orWhereIn('place_id', $userPlaceIds);
                    });
                }
                // Dispositivo recém importado ainda não tem local: quem o vê é o dono,
                // pelo vínculo em device_user. Não pode haver ramo sem escopo aqui —
                // ele expõe os dispositivos sem local de todas as contas.
                if ($hasDeviceUserTable) {
                    $query->orWhereHas('deviceUsers', fn ($q) => $q->where('user_id', Auth::id()));
                }
            })
            ->when($placeFilter === 'unassigned', fn ($query) => $query->whereNull('place_id')->whereDoesntHave('places'))
            ->when(
                $placeFilter !== null && $placeFilter !== 'unassigned',
                function ($query) use ($placeFilter): void {
                    $query->where(function ($query) use ($placeFilter): void {
                        $query->whereHas('places', fn ($q) => $q->where('places.id', (int) $placeFilter))
                            ->orWhere('place_id', (int) $placeFilter);
                    });
                }
            )
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace('%', '\\%', $this->search).'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('external_device_id', 'like', $term)
                        ->orWhere('brand', 'like', $term);
                });
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
        $sharedDevicePlaceIds = collect();
        if (Schema::hasTable('device_user')) {
            $sharedDevicePlaceIds = Device::query()
                ->whereHas('deviceUsers', fn ($q) => $q->where('user_id', Auth::id()))
                ->with('places:id')
                ->get()
                ->flatMap(function (Device $device) {
                    $placeIds = $device->places->pluck('id');
                    if ($device->place_id !== null) {
                        $placeIds->push($device->place_id);
                    }

                    return $placeIds;
                })
                ->unique()
                ->values();
        }

        return $userPlaceIds->merge($sharedDevicePlaceIds)->unique()->filter()->values();
    }
}
