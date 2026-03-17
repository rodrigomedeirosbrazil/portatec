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
    /** @var array<int, int> */
    public array $placeIds = [];

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
            $this->placeIds = [$place->id];
        } elseif ($this->placeIds === []) {
            $defaultPlaceId = Auth::user()->placeUsers()->value('place_id');
            if ($defaultPlaceId !== null) {
                $this->placeIds = [$defaultPlaceId];
            }
        }
    }

    protected function rules(): array
    {
        return [
            'placeIds' => ['required', 'array', 'min:1'],
            'placeIds.*' => ['required', 'integer', 'exists:places,id'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'in:portatec,tuya'],
            'external_device_id' => ['nullable', 'string', 'max:255'],
            'default_pin' => ['nullable', 'string', 'digits:6'],
        ];
    }

    public function save(): mixed
    {
        $validated = $this->validate();

        $placeIds = collect($validated['placeIds'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedPlaceIds = Auth::user()
            ->placeUsers()
            ->whereIn('place_id', $placeIds)
            ->pluck('place_id')
            ->all();

        abort_unless(count($allowedPlaceIds) === count($placeIds), 403);

        $primaryPlaceId = $placeIds[0] ?? null;

        $device = Device::create([
            'place_id' => $primaryPlaceId,
            'name' => $validated['name'],
            'brand' => DeviceBrandEnum::from($validated['brand']),
            'external_device_id' => $validated['external_device_id'] ?: null,
            'default_pin' => $validated['default_pin'] ?: null,
        ]);

        $device->places()->sync($placeIds);

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
