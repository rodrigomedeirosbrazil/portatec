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
                            @if (! $placeDevice->device->isAvailable())
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">
                                    Offline
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4">
                        @if ($placeDevice->device->type === DeviceTypeEnum::Button)
                            <button
                                wire:click="pushButton({{ $placeDevice->device_id }})"
                                @disabled(isset($loadingDevices[$placeDevice->device_id]) || !$placeDevice->device->isAvailable())
                                @class([
                                    'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all duration-200',
                                    'bg-primary-600 text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-0' => $placeDevice->device->status === $placeDevice->device->payload_off && $placeDevice->device->isAvailable() && !isset($loadingDevices[$placeDevice->device_id]),
                                    'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-offset-0' => $placeDevice->device->status === $placeDevice->device->payload_on && $placeDevice->device->isAvailable() && !isset($loadingDevices[$placeDevice->device_id]),
                                    'cursor-not-allowed opacity-70' => !$placeDevice->device->isAvailable() || isset($loadingDevices[$placeDevice->device_id]),
                                ])
                            >
                                @if (isset($loadingDevices[$placeDevice->device_id]))
                                    <svg class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>{{ __('app.sending') }}</span>
                                @else
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                                    </svg>
                                    <span>Push</span>
                                @endif
                            </button>
                        @endif

                        @if ($placeDevice->device->type === DeviceTypeEnum::Switch)
                            <button
                                wire:click="toggleDevice({{ $placeDevice->device_id }})"
                                @disabled(isset($loadingDevices[$placeDevice->device_id]) || !$placeDevice->device->isAvailable())
                                @class([
                                    'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all duration-200',
                                    'bg-primary-600 text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-0' => $placeDevice->device->isAvailable() && !isset($loadingDevices[$placeDevice->device_id]),
                                    'cursor-not-allowed opacity-70' => !$placeDevice->device->isAvailable() || isset($loadingDevices[$placeDevice->device_id]),
                                ])
                            >
                                @if (isset($loadingDevices[$placeDevice->device_id]))
                                    <svg class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>{{ __('app.sending') }}</span>
                                @else
                                    {{ $placeDevice->device->status === $placeDevice->device->payload_on ? 'On' : 'Off' }}
                                @endif
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

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('remove-loading', (event) => {
            setTimeout(() => {
                Livewire.dispatch('removeLoading', { deviceId: event.deviceId });
            }, 1500); // Remove loading state after 1.5 seconds
        });
    });
</script>
</x-filament::card>
