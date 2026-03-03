@props([
    'deviceId',
    'controlFunction',
    'placeId',
    'statusFunction' => null,
    'wireClick' => null,
])

@php
    $sensorKey = $statusFunction ? $deviceId . '-' . $statusFunction->pin : null;
    $initialStatusLabel = $statusFunction && $statusFunction->status !== null
        ? ($statusFunction->status ? __('app.device_statuses.open') : __('app.device_statuses.closed'))
        : '';
@endphp

<div class="mb-2.5 rounded-lg border border-neutral-200 p-3" {{ $attributes }}>
    <p class="m-0 mb-2.5 text-neutral-700">
        {{ $controlFunction->type->label() }} (PIN {{ $controlFunction->pin }})
    </p>

    @if ($statusFunction && $sensorKey !== null)
        <p class="m-0 mb-2.5 text-sm text-neutral-600">
            <span class="rounded-full bg-neutral-100 px-2 py-0.5 font-medium" x-text="typeof statusLabel === 'function' ? statusLabel('{{ $sensorKey }}', '{{ addslashes($initialStatusLabel) }}') : '{{ addslashes($initialStatusLabel) }}'"></span>
        </p>
    @endif

    @if ($controlFunction->type->value === 'button')
        <button
            type="button"
            @if ($wireClick) wire:click="{{ $wireClick }}" @endif
            @click="triggerCommand({{ $deviceId }}, 'push_button', '{{ $controlFunction->pin }}')"
            :disabled="isBusy({{ $deviceId }}, '{{ $controlFunction->pin }}')"
            class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <template x-if="status({{ $deviceId }}, '{{ $controlFunction->pin }}') === 'idle'">
                <span>Acionar</span>
            </template>
            <template x-if="status({{ $deviceId }}, '{{ $controlFunction->pin }}') === 'sending'">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Enviando…
                </span>
            </template>
            <template x-if="status({{ $deviceId }}, '{{ $controlFunction->pin }}') === 'sent'">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Aguardando dispositivo…
                </span>
            </template>
            <template x-if="status({{ $deviceId }}, '{{ $controlFunction->pin }}') === 'acked'">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                    </svg>
                    OK!
                </span>
            </template>
        </button>
    @elseif ($controlFunction->type->value === 'switch')
        <button
            type="button"
            @if ($wireClick) wire:click="{{ $wireClick }}" @endif
            @click="triggerCommand({{ $deviceId }}, 'toggle', '{{ $controlFunction->pin }}')"
            :disabled="isBusy({{ $deviceId }}, '{{ $controlFunction->pin }}')"
            class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <template x-if="status({{ $deviceId }}, '{{ $controlFunction->pin }}') === 'idle'">
                <span>Alternar</span>
            </template>
            <template x-if="status({{ $deviceId }}, '{{ $controlFunction->pin }}') === 'sending'">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Enviando…
                </span>
            </template>
            <template x-if="status({{ $deviceId }}, '{{ $controlFunction->pin }}') === 'sent'">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Aguardando dispositivo…
                </span>
            </template>
            <template x-if="status({{ $deviceId }}, '{{ $controlFunction->pin }}') === 'acked'">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                    </svg>
                    OK!
                </span>
            </template>
        </button>
    @endif
</div>
