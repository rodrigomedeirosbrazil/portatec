<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Models\Integration;
use App\Models\Place;
use App\Models\Platform;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    public ?int $platformId = null;

    public ?int $placeId = null;

    public string $externalId = '';

    public function mount(): void
    {
        if ($this->placeId === null) {
            $this->placeId = Auth::user()->placeUsers()->value('place_id');
        }
    }

    protected function rules(): array
    {
        return [
            'platformId' => ['required', 'integer', 'exists:platforms,id'],
            'placeId' => ['required', 'integer', 'exists:places,id'],
            'externalId' => ['required', 'string', 'max:2000', 'url'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        $hasAccess = Auth::user()
            ->placeUsers()
            ->where('place_id', $validated['placeId'])
            ->exists();

        abort_unless($hasAccess, 403);

        $integration = Integration::firstOrCreate([
            'platform_id' => $validated['platformId'],
            'user_id' => Auth::id(),
        ]);

        $integration->places()->syncWithoutDetaching([
            $validated['placeId'] => ['external_id' => $validated['externalId']],
        ]);

        session()->flash('status', 'Integração criada com sucesso.');

        return $this->redirectRoute('app.integrations.index', navigate: true);
    }

    public function render(): View
    {
        $platforms = Platform::query()->orderBy('name')->get();
        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        if ($this->platformId === null) {
            $this->platformId = $platforms->first()?->id;
        }

        return view('livewire.integrations.create', [
            'platforms' => $platforms,
            'places' => $places,
        ])->layout('layouts.client');
    }
}
