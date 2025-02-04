<?php
    use App\Enums\DeviceTypeEnum;
?>

<x-filament-panels::page>
    @foreach ($place->placeDevices as $placeDevice)
        @if ($placeDevice->device->type === DeviceTypeEnum::Button)
            <x-filament::button icon="heroicon-m-play" >
                {{ $placeDevice->device->name }}
            </x-filament::button>
        @endif
        @if ($placeDevice->device->type === DeviceTypeEnum::Switch)
            <x-filament::button icon="heroicon-m-play" >
                {{ $placeDevice->device->name }}
            </x-filament::button>
        @endif
        @if ($placeDevice->device->type === DeviceTypeEnum::Sensor)
            <div>
                {{ $placeDevice->device->name }}: {{ $placeDevice->device->value ?? 'N/A' }}
            </div>
        @endif
    @endforeach
</x-filament-panels::page>
