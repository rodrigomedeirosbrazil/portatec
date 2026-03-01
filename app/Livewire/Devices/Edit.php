<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Enums\DeviceBrandEnum;
use App\Enums\DeviceTypeEnum;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Models\Place;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public Device $device;

    public ?int $placeId = null;

    public string $name = '';

    public string $brand = 'portatec';

    public ?string $external_device_id = null;

    public ?string $default_pin = null;

    /** @var array<int, array{id: ?int, type: string, pin: string}> */
    public array $deviceFunctions = [];

    public function mount(Device $device): void
    {
        $this->device = $device->load('deviceFunctions');

        abort_unless(
            $this->device->place_id !== null
            && Auth::user()->placeUsers()->where('place_id', $this->device->place_id)->exists(),
            403
        );

        $this->placeId = $this->device->place_id;
        $this->name = $this->device->name;
        $this->brand = $this->device->brand->value;
        $this->external_device_id = $this->device->external_device_id;
        $this->default_pin = $this->device->default_pin;

        foreach ($this->device->deviceFunctions as $fn) {
            $this->deviceFunctions[] = [
                'id' => $fn->id,
                'type' => $fn->type->value,
                'pin' => $fn->pin,
            ];
        }

        if (empty($this->deviceFunctions)) {
            $this->addFunction();
        }
    }

    public function addFunction(): void
    {
        $this->deviceFunctions[] = [
            'id' => null,
            'type' => DeviceTypeEnum::Switch->value,
            'pin' => '',
        ];
    }

    public function removeFunction(int $index): void
    {
        unset($this->deviceFunctions[$index]);
        $this->deviceFunctions = array_values($this->deviceFunctions);
    }

    protected function rules(): array
    {
        return [
            'placeId' => ['required', 'integer', 'exists:places,id'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'in:portatec,tuya'],
            'external_device_id' => ['nullable', 'string', 'max:255'],
            'default_pin' => ['nullable', 'string', 'digits:6'],
            'deviceFunctions' => ['required', 'array', 'min:1'],
            'deviceFunctions.*.id' => ['nullable', 'integer', 'exists:device_functions,id'],
            'deviceFunctions.*.type' => ['required', 'string', 'in:switch,sensor,button'],
            'deviceFunctions.*.pin' => ['required', 'string', 'max:255'],
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

        $this->device->update([
            'place_id' => $validated['placeId'],
            'name' => $validated['name'],
            'brand' => DeviceBrandEnum::from($validated['brand']),
            'external_device_id' => $validated['external_device_id'] ?: null,
            'default_pin' => $validated['default_pin'] ?: null,
        ]);

        $existingIds = collect($validated['deviceFunctions'])
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $this->device->deviceFunctions()
            ->whereNotIn('id', $existingIds)
            ->delete();

        foreach ($validated['deviceFunctions'] as $fn) {
            if (isset($fn['id']) && $fn['id'] !== null) {
                DeviceFunction::query()
                    ->where('id', $fn['id'])
                    ->where('device_id', $this->device->id)
                    ->update([
                        'type' => $fn['type'],
                        'pin' => $fn['pin'],
                    ]);
            } else {
                DeviceFunction::create([
                    'device_id' => $this->device->id,
                    'type' => $fn['type'],
                    'pin' => $fn['pin'],
                ]);
            }
        }

        session()->flash('status', 'Dispositivo atualizado com sucesso.');

        return $this->redirectRoute('app.devices.show', ['device' => $this->device->id], navigate: true);
    }

    public function render(): View
    {
        $places = Place::query()
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        return view('livewire.devices.edit', [
            'places' => $places,
            'brands' => DeviceBrandEnum::cases(),
            'deviceTypes' => DeviceTypeEnum::cases(),
        ])->layout('layouts.client');
    }
}
