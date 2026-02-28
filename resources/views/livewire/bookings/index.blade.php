<section>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="m-0">Bookings</h1>
        <a href="{{ route('app.bookings.create') }}" class="rounded-lg bg-primary-500 px-3 py-2 text-white no-underline hover:bg-primary-700">
            Novo Booking
        </a>
    </div>

    <div class="mb-4">
        <label for="place-filter">Place</label>
        <select id="place-filter" wire:model.live="placeId" class="ml-2 rounded border border-neutral-300 px-2 py-1.5">
            @foreach ($places as $place)
                <option value="{{ $place->id }}">{{ $place->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid gap-3">
        @forelse ($bookings as $booking)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <h2 class="mb-2 text-lg">
                    <a href="{{ route('app.bookings.show', $booking->id) }}" class="text-neutral-900 no-underline hover:text-neutral-700">
                        {{ $booking->guest_name ?: 'Sem nome' }}
                    </a>
                </h2>
                <p class="m-0 text-neutral-500">
                    {{ $booking->check_in->format('d/m/Y H:i') }} até {{ $booking->check_out->format('d/m/Y H:i') }}
                </p>
            </article>
        @empty
            <p class="text-neutral-500">Nenhum booking encontrado.</p>
        @endforelse
    </div>
</section>
