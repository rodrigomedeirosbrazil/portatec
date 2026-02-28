<section>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="m-0">Dispositivos</h1>
    </div>

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <label for="place-filter" class="mb-2 block font-semibold">Filtrar por local</label>
        <select id="place-filter" wire:model.live="placeId" class="max-w-[360px] w-full rounded-lg border border-neutral-300 p-2">
            <option value="">Todos</option>
            @foreach ($places as $place)
                <option value="{{ $place->id }}">{{ $place->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid gap-3">
        @forelse ($devices as $device)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <h2 class="mb-2 text-lg">
                    <a href="{{ route('app.devices.show', $device->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                        {{ $device->name }}
                    </a>
                </h2>
                <p class="m-0 text-neutral-500">Local: {{ $device->place?->name ?? 'Sem local' }}</p>
                <p class="mt-1 m-0 text-neutral-500">Marca: {{ $device->brand->value ?? $device->brand }}</p>
                <p class="mt-1 m-0 text-neutral-500">Online: {{ $device->isAvailable() ? 'Sim' : 'Não' }}</p>
                <p class="mt-1 m-0 text-neutral-500">Funções: {{ $device->device_functions_count }}</p>
                <div class="mt-2.5 flex gap-2">
                    <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-700 no-underline hover:text-primary-500">Detalhes</a>
                    <a href="{{ route('app.devices.control', $device->id) }}" class="text-primary-700 no-underline hover:text-primary-500">Controlar</a>
                </div>
            </article>
        @empty
            <p class="text-neutral-500">Nenhum dispositivo encontrado para os locais selecionados.</p>
        @endforelse
    </div>
</section>
