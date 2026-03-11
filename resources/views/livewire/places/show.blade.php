<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.places.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">{{ $place->name }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('app.places.control', $place->id) }}" class="rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700">
                Controle
            </a>
            <a href="{{ route('app.places.edit', $place->id) }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-neutral-700 no-underline hover:bg-neutral-50">
                Editar
            </a>
            @can('manageMembers', $place)
                <a href="{{ route('app.places.members', $place->id) }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-neutral-700 no-underline hover:bg-neutral-50">
                    {{ __('app.manage_members') }}
                </a>
            @endcan
            @can('replicate', $place)
                <a href="{{ route('app.places.clone', $place->id) }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-neutral-700 no-underline hover:bg-neutral-50">
                    {{ __('app.clone_place') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="mb-4 grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>Dispositivos</strong>
            <p class="mt-1.5 m-0">{{ $place->devices->count() }}</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>Reservas (últimos 10)</strong>
            <p class="mt-1.5 m-0">{{ $place->bookings->count() }}</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <strong>PINs ativos</strong>
            <p class="mt-1.5 m-0">{{ $activeAccessCodes }}</p>
        </div>
    </div>

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="mt-0">{{ __('app.members') }}</h2>
            @can('manageMembers', $place)
                <a href="{{ route('app.places.members', $place->id) }}" class="rounded-lg bg-primary-500 px-3 py-2 text-sm text-white no-underline hover:bg-primary-700">
                    {{ __('app.manage_members') }}
                </a>
            @endcan
        </div>
        <ul class="m-0 pl-5">
            @forelse ($place->placeUsers as $placeUser)
                <li>{{ $placeUser->user->name }}@if ($placeUser->label) ({{ $placeUser->label }})@endif — {{ $placeUser->role === 'admin' ? __('app.place_roles.admin') : __('app.place_roles.host') }}</li>
            @empty
                <li class="text-neutral-500">Apenas você tem acesso a este local.</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="mt-0">Dispositivos</h2>
            <a href="{{ route('app.places.devices.attach', $place->id) }}" class="rounded-lg bg-primary-500 px-3 py-2 text-sm text-white no-underline hover:bg-primary-700">
                Adicionar dispositivo
            </a>
        </div>
        <ul class="m-0 pl-5">
            @forelse ($place->devices as $device)
                <li class="flex flex-wrap items-center gap-2 py-1">
                    <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-700 no-underline hover:text-primary-500">
                        {{ $device->name }}
                    </a>
                    <span class="text-neutral-500">({{ $device->brand->value ?? $device->brand }})</span>
                    <button
                        type="button"
                        wire:click="removeDevice({{ $device->id }})"
                        wire:confirm="Remover o dispositivo &quot;{{ $device->name }}&quot; deste local? Ele continuará existindo e poderá ser associado a outro local."
                        class="rounded border border-red-200 bg-red-50 px-2 py-1 text-sm text-red-700 hover:bg-red-100"
                    >
                        Remover do local
                    </button>
                </li>
            @empty
                <li>Nenhum dispositivo associado.</li>
            @endforelse
        </ul>
    </div>
</section>
