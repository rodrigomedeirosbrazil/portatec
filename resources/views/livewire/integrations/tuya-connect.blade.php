<section>
    <a href="{{ route('app.devices.integrations.index') }}"
       class="text-primary-500 no-underline hover:text-primary-700">
        &larr; Voltar
    </a>
    <h1 class="my-2 mb-4">Conectar via Tuya SmartLife</h1>

    @if ($errorMessage)
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2.5 text-red-700">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($step === 'form')
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <h2 class="mb-1 text-lg font-semibold">Passo 1 — Código do usuário</h2>
            <p class="mb-4 text-sm text-neutral-600">
                Abra o app <strong>Tuya Smart</strong> ou <strong>Smart Life</strong>
                → aba <strong>Eu</strong> → ícone de engrenagem
                → <strong>Conta e Segurança</strong> → <strong>Código do Usuário</strong>.
            </p>

            <form wire:submit="generateQr" class="grid gap-3">
                <div>
                    <label for="userCode" class="text-sm font-medium">
                        Código do Usuário (User Code)
                    </label>
                    <input
                        id="userCode"
                        type="text"
                        wire:model="userCode"
                        placeholder="Ex: eu1234567890abcd"
                        class="mt-1 w-full rounded-lg border border-neutral-300 p-2 font-mono text-sm"
                        autocomplete="off"
                    >
                    @error('userCode')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="cursor-pointer rounded-lg border-0 bg-primary-500 px-4 py-2
                           text-white hover:bg-primary-700 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="generateQr">Gerar QR Code</span>
                    <span wire:loading wire:target="generateQr">Gerando...</span>
                </button>
            </form>
        </div>
    @endif

    @if ($step === 'qr')
        <div wire:poll.3000ms="pollQr"
             class="rounded-[10px] border border-neutral-300 bg-white p-3.5 text-center">
            <h2 class="mb-1 text-lg font-semibold">Passo 2 — Escaneie o QR Code</h2>
            <p class="mb-4 text-sm text-neutral-600">
                Abra o app, toque no ícone de QR na tela inicial e aponte para o código abaixo.
            </p>

            <div class="mx-auto mb-4 flex items-center justify-center">
                <img
                    src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($qrUrl) }}"
                    alt="QR Code Tuya"
                    class="rounded-lg border border-neutral-200"
                    width="220" height="220"
                >
            </div>

            <p class="mb-2 font-mono text-xs text-neutral-400 break-all">{{ $qrUrl }}</p>

            <div class="mt-4 flex items-center justify-center gap-2 text-sm text-neutral-500">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                Aguardando confirmação no app...
            </div>

            @if ($qrExpiresAt)
                <p class="mt-2 text-xs text-neutral-400">
                    O QR expira às
                    {{ \Carbon\Carbon::createFromTimestamp($qrExpiresAt)->format('H:i:s') }}
                </p>
            @endif

            <button wire:click="resetQr"
                    type="button"
                    class="mt-4 cursor-pointer rounded-lg border border-neutral-300
                           bg-white px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100">
                Cancelar
            </button>
        </div>
    @endif

    @if ($step === 'devices')
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <h2 class="mb-1 text-lg font-semibold">Passo 3 — Selecione os dispositivos</h2>
            <p class="mb-4 text-sm text-neutral-600">
                {{ count($devices) }} dispositivo(s) encontrado(s).
                Os dispositivos de acesso já estão pré-selecionados.
            </p>

            <div class="grid gap-2 mb-4">
                @forelse ($devices as $device)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-2.5
                                  transition-colors
                                  {{ ($device['selected'] ?? false)
                                     ? 'border-primary-400 bg-primary-50'
                                     : 'border-neutral-200 bg-white hover:bg-neutral-50' }}">
                        <input
                            type="checkbox"
                            wire:click="toggleDevice({{ json_encode($device['id']) }})"
                            @checked($device['selected'] ?? false)
                            class="h-4 w-4 rounded accent-primary-500"
                        >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium">{{ $device['name'] }}</span>
                                @if ($device['online'] ?? false)
                                    <span class="text-xs font-medium text-green-600">online</span>
                                @else
                                    <span class="text-xs text-neutral-400">offline</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-neutral-500">{{ $device['categoryLabel'] ?? '' }}</p>
                            <p class="mt-0.5 font-mono text-xs text-neutral-400">{{ $device['id'] }}</p>
                        </div>
                    </label>
                @empty
                    <p class="py-4 text-center text-sm text-neutral-500">
                        Nenhum dispositivo encontrado na conta.
                    </p>
                @endforelse
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    wire:click="saveIntegration"
                    wire:loading.attr="disabled"
                    @if (collect($devices)->where('selected', true)->isEmpty()) disabled @endif
                    class="cursor-pointer rounded-lg border-0 bg-primary-500 px-4 py-2
                           text-white hover:bg-primary-700 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="saveIntegration">Salvar Integração</span>
                    <span wire:loading wire:target="saveIntegration">Salvando...</span>
                </button>
                <button type="button"
                        wire:click="resetQr"
                        class="cursor-pointer rounded-lg border border-neutral-300 bg-white
                               px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-100">
                    Cancelar
                </button>
            </div>
        </div>
    @endif

    @if ($step === 'done')
        <div class="rounded-[10px] border border-green-300 bg-green-50 p-5 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-green-800">Integração Tuya conectada com sucesso!</h2>
            <p class="mt-1 text-sm text-green-700">Os dispositivos selecionados foram importados.</p>

            <div class="mt-5 flex justify-center gap-3">
                <a href="{{ route('app.devices.index') }}" wire:navigate
                   class="rounded-lg border-0 bg-primary-500 px-4 py-2
                          text-white no-underline hover:bg-primary-700">
                    Ver dispositivos
                </a>
                <a href="{{ route('app.devices.integrations.index') }}" wire:navigate
                   class="rounded-lg border border-neutral-300 bg-white px-4 py-2
                          text-sm text-neutral-600 no-underline hover:bg-neutral-100">
                    Ver integrações
                </a>
            </div>
        </div>
    @endif
</section>
