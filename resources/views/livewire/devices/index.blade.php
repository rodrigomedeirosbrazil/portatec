<section>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="m-0">Dispositivos</h1>
        <div class="flex gap-2">
            <a href="{{ route('app.devices.integrations.index') }}" class="rounded-lg border border-primary-500 bg-white px-3 py-2 text-primary-600 no-underline hover:bg-primary-50">
                Integrações
            </a>
            <a href="{{ route('app.devices.create') }}" class="rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700">
                Novo dispositivo
            </a>
        </div>
    </div>

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div class="grid gap-3 md:grid-cols-2">
            <x-place-select
                :places="$places"
                wire:model.live="placeId"
                label="Filtrar por local"
                :include-empty="true"
                empty-option-label="Todos"
                :include-unassigned="true"
                unassigned-option-label="Sem local"
                id="place-filter"
            />
            <div>
                <label for="device-search" class="mb-2 block font-semibold">Buscar</label>
                <input
                    id="device-search"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Nome, marca ou ID externo"
                    class="w-full max-w-[360px] rounded-lg border border-neutral-300 p-2"
                    autocomplete="off"
                >
            </div>
        </div>
    </div>

    <div class="grid gap-3">
        @forelse ($devices as $device)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <h2 class="mb-2 text-lg">
                    <a href="{{ route('app.devices.show', $device->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                        {{ $device->name }}
                    </a>
                </h2>
                <p class="m-0 text-neutral-500">
                    Locais: {{ $device->places->pluck('name')->join(', ') ?: 'Sem local' }}
                </p>
                <p class="mt-1 m-0 text-neutral-500">Marca: {{ $device->brand->value ?? $device->brand }}</p>
                <p class="mt-1 m-0 text-neutral-500">Online: {{ $device->isAvailable() ? 'Sim' : 'Não' }}</p>
                <p class="mt-1 m-0 text-neutral-500">Funções: {{ $device->device_functions_count }}</p>
                <div class="mt-2.5 flex gap-2">
                    <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-700 no-underline hover:text-primary-500">Detalhes</a>
                    <a href="{{ route('app.devices.control', $device->id) }}" class="text-primary-700 no-underline hover:text-primary-500">Controlar</a>
                </div>
            </article>
        @empty
            <p class="text-neutral-500">Nenhum dispositivo encontrado com os filtros atuais.</p>
        @endforelse
    </div>
</section>
