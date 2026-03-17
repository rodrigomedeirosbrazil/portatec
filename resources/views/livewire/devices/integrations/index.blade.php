<section>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <a href="{{ route('app.devices.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar para dispositivos</a>
            <h1 class="m-0 mt-2">Integrações de dispositivos</h1>
            <p class="m-0 text-sm text-neutral-500">Conecte contas Tuya para importar e gerenciar dispositivos.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('app.devices.integrations.tuya-connect') }}"
               wire:navigate
               class="rounded-lg border border-primary-500 bg-white px-3 py-2 text-primary-600 no-underline hover:bg-primary-50">
                Conectar Tuya
            </a>
        </div>
    </div>

    <div class="grid gap-3">
        @forelse ($integrations as $integration)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-lg">
                        {{ $integration->platform?->name ?? 'Tuya' }}
                    </h2>
                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">Conectado</span>
                </div>
                <p class="m-0 text-neutral-500">Conta: {{ $integration->tuya_uid ?? 'Nao informado' }}</p>
                @if($integration->tuya_user_code)
                    <p class="mt-1 m-0 text-neutral-500">Codigo do usuario: {{ $integration->tuya_user_code }}</p>
                @endif
                <p class="mt-1 m-0 text-neutral-500">Atualizado em {{ $integration->updated_at?->format('d/m/Y H:i') }}</p>
            </article>
        @empty
            <p class="text-neutral-500">Nenhuma integracao de dispositivos encontrada.</p>
        @endforelse
    </div>
</section>
