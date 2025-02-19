<?php
    use App\Enums\DeviceTypeEnum;
?>

<x-filament::card>
<div class="mb-4">
    <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
        {{ $place->name }}
    </h2>
</div>
<div class="p-4 md:p-6 space-y-2">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach ($place->placeDevices as $placeDevice)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 mb-2">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $placeDevice->device->name }}
                            </h3>
                            @if (! $placeDevice->device->is_available)
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">
                                    Offline
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4">
                        @if ($placeDevice->device->type === DeviceTypeEnum::Button)
                            <livewire:swipe-to-unlock />
                        @endif

                        @if ($placeDevice->device->type === DeviceTypeEnum::Switch)
                            <button
                                wire:click="toggleDevice({{ $placeDevice->device_id }})"
                                @class([
                                    'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold',
                                    'bg-primary-600 text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-0' => $placeDevice->device->is_available && $placeDevice->device->status === $placeDevice->device->payload_on,
                                    'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-offset-0' => $placeDevice->device->is_available && $placeDevice->device->status === $placeDevice->device->payload_off,
                                    'cursor-not-allowed opacity-70' => ! $placeDevice->device->is_available,
                                ])
                            >
                                {{ $placeDevice->device->status === $placeDevice->device->payload_on ? 'On' : 'Off' }}
                            </button>
                        @endif

                        @if ($placeDevice->device->type === DeviceTypeEnum::Sensor)
                            <div class="flex items-center space-x-2">
                                <span class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    @if (! empty($placeDevice->device->status))
                                        {{ $placeDevice->device->status }}
                                    @else
                                        Not available
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
</x-filament::card>
