<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.devices.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">{{ $device->name }}</h1>
        </div>
        <a href="{{ route('app.devices.control', $device->id) }}" class="rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700">
            Controlar
        </a>
    </div>

    <div class="mb-4 grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>Local</strong>
            <p class="mt-1.5 m-0">{{ $device->place?->name ?? 'Sem local' }}</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>Marca</strong>
            <p class="mt-1.5 m-0">{{ $device->brand->value ?? $device->brand }}</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>Status</strong>
            <p class="mt-1.5 m-0">{{ $device->isAvailable() ? 'Online' : 'Offline' }}</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>Última sincronização</strong>
            <p class="mt-1.5 m-0">{{ $device->last_sync?->format('d/m/Y H:i:s') ?? 'Nunca sincronizado' }}</p>
        </div>
    </div>

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <h2 class="mt-0">Funções</h2>
        <ul class="m-0 pl-5">
            @forelse ($device->deviceFunctions as $function)
                <li>
                    {{ $function->type->label() }} | PIN {{ $function->pin }}
                    @if (!is_null($function->status))
                        | Status: {{ $function->status ? 'Ligado' : 'Desligado' }}
                    @endif
                </li>
            @empty
                <li>Nenhuma função cadastrada.</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <h2 class="mt-0">Últimos comandos</h2>
        <ul class="m-0 pl-5">
            @forelse ($recentCommands as $command)
                <li>
                    {{ $command->created_at?->format('d/m/Y H:i:s') }} - {{ $command->command_type }}
                </li>
            @empty
                <li>Nenhum comando registrado.</li>
            @endforelse
        </ul>
    </div>
</section>
