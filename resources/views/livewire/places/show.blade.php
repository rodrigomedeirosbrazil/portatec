<section>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
            <a href="{{ route('app.places.index') }}" style="color: #2563eb; text-decoration: none;">&larr; Voltar</a>
            <h1 style="margin: 8px 0 0;">{{ $place->name }}</h1>
        </div>
        <a href="{{ route('app.places.edit', $place->id) }}" style="background: #111827; color: #fff; text-decoration: none; border-radius: 8px; padding: 8px 12px;">
            Editar
        </a>
    </div>

    <div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 16px;">
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
            <strong>Dispositivos</strong>
            <p style="margin: 6px 0 0;">{{ $place->devices->count() }}</p>
        </div>
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
            <strong>Bookings (últimos 10)</strong>
            <p style="margin: 6px 0 0;">{{ $place->bookings->count() }}</p>
        </div>
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
            <strong>PINs ativos</strong>
            <p style="margin: 6px 0 0;">{{ $activeAccessCodes }}</p>
        </div>
    </div>

    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
        <h2 style="margin-top: 0;">Dispositivos</h2>
        <ul style="margin: 0; padding-left: 20px;">
            @forelse ($place->devices as $device)
                <li>
                    <a href="{{ route('app.devices.show', $device->id) }}" style="color: #1d4ed8; text-decoration: none;">
                        {{ $device->name }}
                    </a>
                    ({{ $device->brand->value ?? $device->brand }})
                </li>
            @empty
                <li>Nenhum dispositivo associado.</li>
            @endforelse
        </ul>
    </div>
</section>
