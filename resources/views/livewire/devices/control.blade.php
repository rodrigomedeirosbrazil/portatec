<section>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
            <a href="{{ route('app.devices.show', $device->id) }}" style="color: #2563eb; text-decoration: none;">&larr; Voltar</a>
            <h1 style="margin: 8px 0 0;">Controlar {{ $device->name }}</h1>
        </div>
    </div>

    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
        <h2 style="margin-top: 0;">Ações disponíveis</h2>

        @forelse ($controllableFunctions as $function)
            <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                <p style="margin: 0 0 10px; color: #374151;">
                    {{ $function->type->label() }} (PIN {{ $function->pin }})
                </p>

                @if ($function->type->value === 'button')
                    <button
                        wire:click="sendCommand('push_button', '{{ $function->pin }}')"
                        wire:loading.attr="disabled"
                        style="background: #111827; color: #fff; border: 0; border-radius: 8px; padding: 8px 12px; cursor: pointer;"
                    >
                        Acionar
                    </button>
                @elseif ($function->type->value === 'switch')
                    <button
                        wire:click="sendCommand('toggle', '{{ $function->pin }}')"
                        wire:loading.attr="disabled"
                        style="background: #111827; color: #fff; border: 0; border-radius: 8px; padding: 8px 12px; cursor: pointer;"
                    >
                        Alternar
                    </button>
                @endif
            </div>
        @empty
            <p style="margin: 0; color: #4b5563;">Nenhuma função controlável encontrada para este dispositivo.</p>
        @endforelse
    </div>
</section>
