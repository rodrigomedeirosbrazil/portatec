<section>
    <x-page-header title="Dispositivos" :action-url="route('app.devices.create')" action-label="Novo Dispositivo" />

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="min-w-0">
                <x-place-select
                    :places="$places"
                    wire:model.live="placeId"
                    label="Filtrar por local"
                    :include-empty="true"
                    empty-option-label="Todos"
                    id="place-filter"
                />
            </div>

            <div class="min-w-0">
                <x-search-input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nome..."
                    label="Buscar"
                    id="search"
                />
            </div>
        </div>
    </div>

    @if($devices->total() > 0)
        <p class="mb-3 text-neutral-500">
            Mostrando {{ $devices->firstItem() }}–{{ $devices->lastItem() }} de {{ $devices->total() }} dispositivos.
        </p>
    @endif

    <div class="relative grid gap-3">
        <x-loading-overlay />

        @forelse ($devices as $device)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="flex items-start justify-between">
                    <h2 class="mb-2 text-lg">
                        <a href="{{ route('app.devices.show', $device->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                            {{ $device->name }}
                        </a>
                    </h2>
                    <x-status-badge :status="$device->isAvailable() ? 'online' : 'offline'" />
                </div>
                <p class="m-0 text-neutral-500">Local: {{ $device->place?->name ?? 'Sem local' }}</p>
                <p class="mt-1 m-0 text-neutral-500">Marca: {{ $device->brand->value ?? $device->brand }}</p>
                <p class="mt-1 m-0 text-neutral-500">Funções: {{ $device->device_functions_count }}</p>
                <div class="mt-2.5 flex gap-2">
                    <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-700 no-underline hover:text-primary-500">Detalhes</a>
                    <a href="{{ route('app.devices.control', $device->id) }}" class="text-primary-700 no-underline hover:text-primary-500">Controlar</a>
                </div>
            </article>
        @empty
            <x-empty-state
                message="Nenhum dispositivo encontrado."
                :action-url="route('app.devices.create')"
                action-label="Novo Dispositivo"
            />
        @endforelse
    </div>

    @if($devices->hasPages())
        <div class="mt-4 flex flex-wrap gap-1">
            {{ $devices->links() }}
        </div>
    @endif
</section>
