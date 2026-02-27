<section>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h1 style="margin: 0;">Dispositivos</h1>
    </div>

    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
        <label for="place-filter" style="display: block; margin-bottom: 8px; font-weight: 600;">Filtrar por local</label>
        <select id="place-filter" wire:model.live="placeId" style="width: 100%; max-width: 360px; padding: 8px; border-radius: 8px; border: 1px solid #d1d5db;">
            <option value="">Todos</option>
            @foreach ($places as $place)
                <option value="{{ $place->id }}">{{ $place->name }}</option>
            @endforeach
        </select>
    </div>

    <div style="display: grid; gap: 12px;">
        @forelse ($devices as $device)
            <article style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
                <h2 style="margin: 0 0 8px; font-size: 18px;">
                    <a href="{{ route('app.devices.show', $device->id) }}" style="color: #111827; text-decoration: none;">
                        {{ $device->name }}
                    </a>
                </h2>
                <p style="margin: 0; color: #4b5563;">Local: {{ $device->place?->name ?? 'Sem local' }}</p>
                <p style="margin: 4px 0 0; color: #4b5563;">Marca: {{ $device->brand->value ?? $device->brand }}</p>
                <p style="margin: 4px 0 0; color: #4b5563;">Online: {{ $device->isAvailable() ? 'Sim' : 'Não' }}</p>
                <p style="margin: 4px 0 0; color: #4b5563;">Funções: {{ $device->device_functions_count }}</p>
                <div style="margin-top: 10px; display: flex; gap: 8px;">
                    <a href="{{ route('app.devices.show', $device->id) }}" style="text-decoration: none; color: #1d4ed8;">Detalhes</a>
                    <a href="{{ route('app.devices.control', $device->id) }}" style="text-decoration: none; color: #1d4ed8;">Controlar</a>
                </div>
            </article>
        @empty
            <p style="color: #4b5563;">Nenhum dispositivo encontrado para os locais selecionados.</p>
        @endforelse
    </div>
</section>
