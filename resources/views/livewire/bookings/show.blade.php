<section>
    <a href="{{ route('app.bookings.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <h1 class="my-2 mb-4">Detalhes do Booking</h1>

    <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <p class="mb-2"><strong>Hóspede:</strong> {{ $booking->guest_name ?: 'Sem nome' }}</p>
        <p class="mb-2"><strong>Check-in:</strong> {{ $booking->check_in->format('d/m/Y H:i') }}</p>
        <p class="mb-2"><strong>Check-out:</strong> {{ $booking->check_out->format('d/m/Y H:i') }}</p>
        <p class="m-0"><strong>PIN:</strong> {{ $booking->accessCode?->pin ?? 'Ainda não gerado' }}</p>
    </div>
</section>
