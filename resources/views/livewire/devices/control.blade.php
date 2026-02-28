<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">Controlar {{ $device->name }}</h1>
        </div>
    </div>

    <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <h2 class="mt-0">Ações disponíveis</h2>

        @forelse ($controllableFunctions as $function)
            <div class="mb-2.5 rounded-lg border border-neutral-300 p-3">
                <p class="m-0 mb-2.5 text-neutral-700">
                    {{ $function->type->label() }} (PIN {{ $function->pin }})
                </p>

                @if ($function->type->value === 'button')
                    <button
                        wire:click="sendCommand('push_button', '{{ $function->pin }}')"
                        wire:loading.attr="disabled"
                        class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:opacity-50"
                    >
                        Acionar
                    </button>
                @elseif ($function->type->value === 'switch')
                    <button
                        wire:click="sendCommand('toggle', '{{ $function->pin }}')"
                        wire:loading.attr="disabled"
                        class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:opacity-50"
                    >
                        Alternar
                    </button>
                @endif
            </div>
        @empty
            <p class="m-0 text-neutral-500">Nenhuma função controlável encontrada para este dispositivo.</p>
        @endforelse
    </div>
</section>
