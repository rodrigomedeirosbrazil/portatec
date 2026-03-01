<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.places.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">{{ $place->name }}</h1>
        </div>
        <a href="{{ route('app.places.edit', $place->id) }}" class="rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700">
            Editar
        </a>
    </div>

    <div class="mb-4 grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>Dispositivos</strong>
            <p class="mt-1.5 m-0">{{ $place->devices->count() }}</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>Bookings (últimos 10)</strong>
            <p class="mt-1.5 m-0">{{ $place->bookings->count() }}</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>PINs ativos</strong>
            <p class="mt-1.5 m-0">{{ $activeAccessCodes }}</p>
        </div>
    </div>

    <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="mt-0">Dispositivos</h2>
            <a href="{{ route('app.places.devices.create', $place->id) }}" class="rounded-lg bg-primary-500 px-3 py-2 text-sm text-white no-underline hover:bg-primary-700">
                Adicionar dispositivo
            </a>
        </div>
        <ul class="m-0 pl-5">
            @forelse ($place->devices as $device)
                <li>
                    <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-700 no-underline hover:text-primary-500">
                        {{ $device->name }}
                    </a>
                    ({{ $device->brand->value ?? $device->brand }})
                </li>
            @empty
                <li>Nenhum dispositivo associado.</li>
            @endforelse
        </ul>
    </div>
</section>
