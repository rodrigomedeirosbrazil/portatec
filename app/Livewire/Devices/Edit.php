<?php

declare(strict_types=1);

namespace App\Livewire\Devices;

use App\Enums\DeviceBrandEnum;
use App\Enums\DeviceTypeEnum;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public Device $device;

    /** @var array<int, int> */
    public array $placeIds = [];

    public string $name = '';

    public string $brand = 'portatec';

    public ?string $external_device_id = null;

    public ?string $default_pin = null;

    /** @var array<int, array{id: ?int, type: string, pin: string}> */
    public array $deviceFunctions = [];

    public function mount(Device $device): void
    {
        $this->device = $device->load(['deviceFunctions', 'places']);

        $devicePlaceIds = $this->device->places->pluck('id')->all();
        $hasAccess = $devicePlaceIds !== []
            ? Auth::user()->placeUsers()->whereIn('place_id', $devicePlaceIds)->exists()
            : Auth::user()->devices()->where('devices.id', $this->device->id)->exists();

        abort_unless($hasAccess, 403);

        $this->placeIds = $devicePlaceIds;
        if ($this->placeIds === [] && $this->device->place_id !== null) {
            $this->placeIds = [$this->device->place_id];
        }
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

    /**
     * @param array<int, int> $placeIds
     */
    private function syncPlaceDeviceFunctions(array $placeIds): void
    {
        $this->device->load('deviceFunctions');

        $functionIds = $this->device->deviceFunctions->pluck('id')->all();

        PlaceDeviceFunction::query()
            ->whereIn('device_function_id', $functionIds)
            ->whereNotIn('place_id', $placeIds)
            ->delete();

        foreach ($placeIds as $placeId) {
            foreach ($functionIds as $deviceFunctionId) {
                PlaceDeviceFunction::firstOrCreate(
                    [
                        'place_id' => $placeId,
                        'device_function_id' => $deviceFunctionId,
                    ]
                );
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
            'deviceFunctions' => ['required', 'array', 'min:1'],
            'deviceFunctions.*.id' => ['nullable', 'integer', 'exists:device_functions,id'],
            'deviceFunctions.*.type' => ['required', 'string', 'in:switch,sensor,button'],
            'deviceFunctions.*.pin' => ['required', 'string', 'max:255'],
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

        $this->device->update([
            'place_id' => $primaryPlaceId,
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

        $this->device->places()->sync($placeIds);
        $this->syncPlaceDeviceFunctions($placeIds);

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
