<section>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
            <a href="{{ route('app.devices.index') }}" style="color: #2563eb; text-decoration: none;">&larr; Voltar</a>
            <h1 style="margin: 8px 0 0;">{{ $device->name }}</h1>
        </div>
        <a href="{{ route('app.devices.control', $device->id) }}" style="background: #111827; color: #fff; text-decoration: none; border-radius: 8px; padding: 8px 12px;">
            Controlar
        </a>
    </div>

    <div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 16px;">
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
            <strong>Local</strong>
            <p style="margin: 6px 0 0;">{{ $device->place?->name ?? 'Sem local' }}</p>
        </div>
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
            <strong>Marca</strong>
            <p style="margin: 6px 0 0;">{{ $device->brand->value ?? $device->brand }}</p>
        </div>
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
            <strong>Status</strong>
            <p style="margin: 6px 0 0;">{{ $device->isAvailable() ? 'Online' : 'Offline' }}</p>
        </div>
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
            <strong>Última sincronização</strong>
            <p style="margin: 6px 0 0;">{{ $device->last_sync?->format('d/m/Y H:i:s') ?? 'Nunca sincronizado' }}</p>
        </div>
    </div>

    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
        <h2 style="margin-top: 0;">Funções</h2>
        <ul style="margin: 0; padding-left: 20px;">
            @forelse ($device->deviceFunctions as $function)
                <li>
                    {{ $function->type->label() }} | PIN {{ $function->pin }}
                    @if (!is_null($function->status))
                        | Status: {{ $function->status ? 'Ligado' : 'Desligado' }}
                    @endif
                </li>
            @empty
                <li>Nenhuma função cadastrada.</li>
            @endforelse
        </ul>
    </div>

    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
        <h2 style="margin-top: 0;">Últimos comandos</h2>
        <ul style="margin: 0; padding-left: 20px;">
            @forelse ($recentCommands as $command)
                <li>
                    {{ $command->created_at?->format('d/m/Y H:i:s') }} - {{ $command->command_type }}
                </li>
            @empty
                <li>Nenhum comando registrado.</li>
            @endforelse
        </ul>
    </div>
</section>
