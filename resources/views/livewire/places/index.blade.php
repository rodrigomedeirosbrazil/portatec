<section>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="m-0">Locais</h1>
        <a href="{{ route('app.places.create') }}" class="rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700">
            Novo Local
        </a>
    </div>

    <div class="grid gap-3">
        @forelse ($places as $place)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <h2 class="mb-2 text-lg">
                    <a href="{{ route('app.places.show', $place->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                        {{ $place->name }}
                    </a>
                </h2>
                <p class="m-0 text-neutral-500">
                    Dispositivos: {{ $place->devices_count }} | Reservas: {{ $place->bookings_count }} | PINs: {{ $place->access_codes_count }}
                </p>
            </article>
        @empty
            <p class="text-neutral-500">Você ainda não possui locais.</p>
        @endforelse
    </div>
</section>
