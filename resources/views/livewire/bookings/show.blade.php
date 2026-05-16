<section>
    <a href="{{ route('app.bookings.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="my-2 m-0">Detalhes da Reserva</h1>
        @if ($canDelete)
            <button
                type="button"
                wire:confirm="Remover esta reserva?"
                wire:click="deleteBooking"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 hover:bg-red-100"
            >
                Remover
            </button>
        @endif
    </div>

    <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <p class="mb-2"><strong>Local:</strong>
            <a href="{{ route('app.places.show', $booking->place_id) }}" class="text-primary-500 no-underline hover:text-primary-700">
                {{ $booking->place?->name ?? 'Sem local' }}
            </a>
        </p>
        <p class="mb-2"><strong>Hóspede:</strong> {{ $booking->guest_name ?: 'Sem nome' }}</p>
        <p class="mb-2"><strong>Check-in:</strong> {{ $booking->check_in->format('d/m/Y H:i') }}</p>
        <p class="mb-2"><strong>Check-out:</strong> {{ $booking->check_out->format('d/m/Y H:i') }}</p>
        @php $nights = (int) $booking->check_in->startOfDay()->diffInDays($booking->check_out->startOfDay()); @endphp
        <p class="mb-2"><strong>Duração:</strong> {{ $nights }} {{ $nights === 1 ? 'noite' : 'noites' }}</p>
        <p class="m-0"><strong>PIN:</strong> {{ $booking->accessCode?->pin ?? 'Ainda não gerado' }}</p>
        @if (! $canDelete)
            <p class="mt-2 text-sm text-neutral-500">
                Reservas sincronizadas via integração não podem ser removidas manualmente.
            </p>
        @endif
    </div>
</section>
