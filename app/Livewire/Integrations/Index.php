<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Models\Integration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
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
        $integrations = Integration::query()
            ->where('user_id', Auth::id())
            ->whereHas('platform', fn ($query) => $query->where('slug', '!=', 'tuya'))
            ->with(['platform', 'places'])
            ->latest('updated_at')
            ->get();

        return view('livewire.integrations.index', [
            'integrations' => $integrations,
        ])->layout('layouts.client');
    }
}
