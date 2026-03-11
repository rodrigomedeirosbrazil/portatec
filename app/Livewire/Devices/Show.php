<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Models\CommandLog;
use App\Models\Device;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public Device $device;

    public function mount(Device $device): void
    {
        $this->device = $device->load(['place', 'deviceFunctions']);

        abort_unless(Auth::user()->can('view', $this->device), 403);
    }

    public function render(): View
    {
        $recentCommands = CommandLog::query()
            ->whereHas('deviceFunction', fn ($query) => $query->where('device_id', $this->device->id))
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.devices.show', [
            'recentCommands' => $recentCommands,
        ])->layout('layouts.client');
    }
}
