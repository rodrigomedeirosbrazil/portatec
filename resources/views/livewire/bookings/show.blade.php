<section>
    <a href="{{ route('app.bookings.index') }}" style="color: #2563eb; text-decoration: none;">&larr; Voltar</a>
    <h1 style="margin: 8px 0 16px;">Detalhes do Booking</h1>

    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
        <p style="margin: 0 0 8px;"><strong>Hóspede:</strong> {{ $booking->guest_name ?: 'Sem nome' }}</p>
        <p style="margin: 0 0 8px;"><strong>Check-in:</strong> {{ $booking->check_in->format('d/m/Y H:i') }}</p>
        <p style="margin: 0 0 8px;"><strong>Check-out:</strong> {{ $booking->check_out->format('d/m/Y H:i') }}</p>
        <p style="margin: 0;"><strong>PIN:</strong> {{ $booking->accessCode?->pin ?? 'Ainda não gerado' }}</p>
    </div>
</section>
