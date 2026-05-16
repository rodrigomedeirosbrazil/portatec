<section>
    <h1 class="m-0 mb-4">Dashboard</h1>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5 text-center">
            <p class="m-0 text-2xl font-bold text-neutral-900">{{ $totalOnline }} / {{ $totalDevices }}</p>
            <p class="m-0 mt-1 text-sm text-neutral-500">Dispositivos online</p>
        </div>
        <div class="rounded-[10px] border {{ $totalOffline > 0 ? 'border-red-300 bg-red-50' : 'border-neutral-300 bg-white' }} p-3.5 text-center">
            <p class="m-0 text-2xl font-bold {{ $totalOffline > 0 ? 'text-red-700' : 'text-neutral-900' }}">{{ $totalOffline }}</p>
            <p class="m-0 mt-1 text-sm {{ $totalOffline > 0 ? 'text-red-500' : 'text-neutral-500' }}">Dispositivos offline</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5 text-center">
            <p class="m-0 text-2xl font-bold text-neutral-900">{{ $activeBookings }}</p>
            <p class="m-0 mt-1 text-sm text-neutral-500">Reservas em andamento</p>
        </div>
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5 text-center">
            <p class="m-0 text-2xl font-bold text-neutral-900">{{ $todayCheckIns }}</p>
            <p class="m-0 mt-1 text-sm text-neutral-500">Check-ins hoje</p>
        </div>
    </div>

    <div class="grid gap-3">
        @forelse ($places as $place)
            @php
                $online = $onlineCountByPlace[$place->id] ?? 0;
                $total = $place->devices_count;
                $hasOffline = $total > 0 && $online < $total;
            @endphp
            <article class="rounded-[10px] border {{ $hasOffline ? 'border-red-300' : 'border-neutral-300' }} bg-white p-3.5">
                <div class="flex items-start justify-between">
                    <h2 class="mb-2 text-lg">
                        <a href="{{ route('app.places.show', $place->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                            {{ $place->name }}
                        </a>
                    </h2>
                    @if($total > 0)
                        <x-status-badge :status="$hasOffline ? 'offline' : 'online'" />
                    @endif
                </div>
                <p class="m-0 text-neutral-500">
                    Dispositivos online: {{ $online }} / {{ $total }}
                </p>
                <p class="mt-1 m-0 text-neutral-500">
                    Próximo check-in:
                    {{ optional($nextCheckInByPlace[$place->id] ?? null)->check_in?->format('d/m/Y H:i') ?? 'Sem reservas futuras' }}
                </p>
                <div class="mt-2 flex gap-2">
                    <a href="{{ route('app.places.control', $place->id) }}" class="text-sm text-primary-700 no-underline hover:text-primary-500">Controlar</a>
                    <a href="{{ route('app.bookings.index', ['place_id' => $place->id]) }}" class="text-sm text-primary-700 no-underline hover:text-primary-500">Reservas</a>
                </div>
            </article>
        @empty
            <x-empty-state
                message="Nenhum local encontrado."
                :action-url="route('app.places.create')"
                action-label="Novo Local"
            />
        @endforelse
    </div>
</section>
