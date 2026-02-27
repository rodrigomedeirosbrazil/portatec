<section>
    <h1 style="margin: 0 0 16px;">Dashboard</h1>

    <div style="display: grid; gap: 12px;">
        @forelse ($places as $place)
            <article style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
                <h2 style="margin: 0 0 8px; font-size: 18px;">
                    <a href="{{ route('app.places.show', $place->id) }}" style="color: #111827; text-decoration: none;">
                        {{ $place->name }}
                    </a>
                </h2>
                <p style="margin: 0; color: #4b5563;">
                    Dispositivos online: {{ $onlineCountByPlace[$place->id] ?? 0 }} / {{ $place->devices_count }}
                </p>
                <p style="margin: 4px 0 0; color: #4b5563;">
                    Próximo check-in:
                    {{ optional($nextCheckInByPlace[$place->id] ?? null)->check_in?->format('d/m/Y H:i') ?? 'Sem reservas futuras' }}
                </p>
            </article>
        @empty
            <p style="color: #4b5563;">Nenhum place encontrado.</p>
        @endforelse
    </div>
</section>
