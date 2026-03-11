<?php

declare(strict_types=1);

namespace App\Livewire\AccessCodes;

use App\Models\AccessCode;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public ?int $placeId = null;

    public function mount(): void
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        if (request()->has('place_id')) {
            $requestedId = (int) request()->input('place_id');
            if ($userPlaceIds->contains($requestedId)) {
                $this->placeId = $requestedId;
            }
        }

        if ($this->placeId === null) {
            $this->placeId = Auth::user()->placeUsers()->value('place_id');
        }
    }

    public function updatedPlaceId()
    {
        $params = $this->placeId !== null ? ['place_id' => $this->placeId] : [];

        return redirect()->to(route('app.access-codes.index', $params));
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $accessCodes = AccessCode::query()
            ->whereIn('place_id', $userPlaceIds)
            ->when($this->placeId, fn ($query) => $query->where('place_id', $this->placeId))
            ->orderBy('start')
            ->limit(100)
            ->get();

        return view('livewire.access-codes.index', [
            'places' => $places,
            'accessCodes' => $accessCodes,
        ])->layout('layouts.client');
    }
}
