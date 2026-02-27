<section>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h1 style="margin: 0;">Integrações</h1>
        <a href="{{ route('app.integrations.create') }}" style="background: #111827; color: #fff; text-decoration: none; border-radius: 8px; padding: 8px 12px;">
            Nova Integração
        </a>
    </div>

    <div style="display: grid; gap: 12px;">
        @forelse ($integrations as $integration)
            <article style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
                <h2 style="margin: 0 0 8px; font-size: 18px;">
                    {{ $integration->platform?->name ?? 'Plataforma' }}
                </h2>
                <p style="margin: 0; color: #4b5563;">
                    Places: {{ $integration->places->pluck('name')->join(', ') ?: 'Nenhum' }}
                </p>
                <p style="margin: 4px 0 0; color: #4b5563;">
                    Última atualização: {{ $integration->updated_at?->format('d/m/Y H:i') }}
                </p>
            </article>
        @empty
            <p style="color: #4b5563;">Nenhuma integração encontrada.</p>
        @endforelse
    </div>
</section>
