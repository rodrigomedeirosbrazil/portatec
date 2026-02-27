<section>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h1 style="margin: 0;">Access Codes</h1>
        <a href="{{ route('app.access-codes.create') }}" style="background: #111827; color: #fff; text-decoration: none; border-radius: 8px; padding: 8px 12px;">
            Novo PIN
        </a>
    </div>

    <div style="margin-bottom: 16px;">
        <label for="place-filter">Place</label>
        <select id="place-filter" wire:model.live="placeId" style="margin-left: 8px; padding: 6px 8px;">
            @foreach ($places as $place)
                <option value="{{ $place->id }}">{{ $place->name }}</option>
            @endforeach
        </select>
    </div>

    <div style="display: grid; gap: 12px;">
        @forelse ($accessCodes as $accessCode)
            <article style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
                <h2 style="margin: 0 0 8px; font-size: 18px;">
                    <a href="{{ route('app.access-codes.edit', $accessCode->id) }}" style="color: #111827; text-decoration: none;">
                        PIN {{ $accessCode->pin }}
                    </a>
                </h2>
                <p style="margin: 0; color: #4b5563;">{{ $accessCode->display_name }}</p>
                <p style="margin: 4px 0 0; color: #4b5563;">
                    {{ $accessCode->start->format('d/m/Y H:i') }} até {{ $accessCode->end?->format('d/m/Y H:i') ?? 'Sem expiração' }}
                </p>
            </article>
        @empty
            <p style="color: #4b5563;">Nenhum access code encontrado.</p>
        @endforelse
    </div>
</section>
