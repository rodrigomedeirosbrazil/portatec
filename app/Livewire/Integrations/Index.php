<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Models\Integration;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public ?int $placeId = null;

    public function deleteIntegration(int $integrationId): void
    {
        $integration = Integration::query()
            ->where('user_id', Auth::id())
            ->whereKey($integrationId)
            ->firstOrFail();

        $integration->places()->detach();
        $integration->delete();

        session()->flash('status', 'Integração removida com sucesso.');
    }

    public function render(): View
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');

        $places = Place::query()
            ->whereIn('id', $userPlaceIds)
            ->orderBy('name')
            ->get();

        $integrations = Integration::query()
            ->where('user_id', Auth::id())
            ->whereHas('platform', fn ($query) => $query->where('slug', '!=', 'tuya'))
            ->with(['platform', 'places'])
            ->when($this->placeId, function ($query): void {
                $query->whereHas('places', fn ($q) => $q->where('places.id', $this->placeId));
            })
            ->latest('updated_at')
            ->get();

        return view('livewire.integrations.index', [
            'integrations' => $integrations,
            'places' => $places,
        ])->layout('layouts.client');
    }
}
