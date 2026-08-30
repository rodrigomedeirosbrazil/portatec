<?php

declare(strict_types=1);

namespace App\Livewire\Devices\Integrations;

use App\Models\Integration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function render(): View
    {
        $integrations = Integration::query()
            ->where('user_id', Auth::id())
            ->whereHas('platform', fn ($query) => $query->where('slug', 'tuya'))
            ->with('platform')
            ->latest('updated_at')
            ->get();

        return view('livewire.devices.integrations.index', [
            'integrations' => $integrations,
        ])->layout('layouts.client');
    }
}
