<section>
    <x-page-header title="Locais" :action-url="route('app.places.create')" action-label="Novo Local" />

    <div class="mb-4 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <x-search-input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nome..."
            id="search"
        />
    </div>

    <div class="relative grid gap-3">
        <x-loading-overlay />

        @forelse ($places as $place)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <h2 class="mb-2 text-lg">
                    <a href="{{ route('app.places.show', $place->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                        {{ $place->name }}
                    </a>
                </h2>
                <p class="m-0 text-neutral-500">
                    Dispositivos: {{ $place->devices_count }} | Reservas: {{ $place->bookings_count }} | Códigos: {{ $place->access_codes_count }}
                </p>
            </article>
        @empty
            <x-empty-state
                message="Você ainda não possui locais."
                :action-url="route('app.places.create')"
                action-label="Novo Local"
            />
        @endforelse
    </div>
</section>
