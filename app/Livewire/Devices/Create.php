<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    public ?int $placeId = null;

    public string $name = '';

    public string $brand = 'portatec';

    public ?string $external_device_id = null;

    public ?string $default_pin = null;

    public function mount(?Place $place = null): void
    {
        if ($place !== null) {
            abort_unless(
                $place->placeUsers()->where('user_id', Auth::id())->exists(),
                403
            );
            $this->placeId = $place->id;
        } elseif ($this->placeId === null) {
            $this->placeId = Auth::user()->placeUsers()->value('place_id');
        }
    }

    protected function rules(): array
    {
        return [
            'placeId' => ['required', 'integer', 'exists:places,id'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'in:portatec,tuya'],
            'external_device_id' => ['nullable', 'string', 'max:255'],
            'default_pin' => ['nullable', 'string', 'digits:6'],
        ];
    }

    public function save(): mixed
    {
        $validated = $this->validate();

        $hasAccess = Auth::user()
            ->placeUsers()
            ->where('place_id', $validated['placeId'])
            ->exists();

        abort_unless($hasAccess, 403);

        $device = Device::create([
            'place_id' => $validated['placeId'],
            'name' => $validated['name'],
            'brand' => DeviceBrandEnum::from($validated['brand']),
            'external_device_id' => $validated['external_device_id'] ?: null,
            'default_pin' => $validated['default_pin'] ?: null,
        ]);

        session()->flash('status', 'Dispositivo criado com sucesso.');

        return $this->redirectRoute('app.devices.show', ['device' => $device->id], navigate: true);
    }

    public function render(): View
    {
        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        return view('livewire.devices.create', [
            'places' => $places,
            'brands' => DeviceBrandEnum::cases(),
        ])->layout('layouts.client');
    }
}
