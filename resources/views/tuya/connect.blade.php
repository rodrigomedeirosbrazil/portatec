<section>
    <h1 class="mb-4">Vincular conta Tuya</h1>
    @if(isset($error))
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-amber-800">
            {{ $error }}
        </div>
    @endif

    <p class="mb-4 text-neutral-600">
        No app Tuya, vá em <strong>Eu</strong> &gt; <strong>Configurações</strong> &gt; <strong>Conta e Segurança</strong> e copie o <strong>Código do Usuário</strong>.
        Depois gere o QR e faça a leitura no app Tuya (ícone de scanner no canto superior direito da tela inicial).
    </p>

    <form method="POST" action="{{ route('app.tuya.connect.start') }}" class="mb-6 flex flex-wrap items-end gap-3">
        @csrf
        <label class="flex flex-col gap-1 text-sm text-neutral-700">
            Código do Usuário
            <input type="text" name="user_code" value="{{ old('user_code', $user_code ?? '') }}" class="w-64 rounded-md border border-neutral-300 px-3 py-2 text-sm" required>
        </label>
        <button type="submit" class="rounded-lg bg-primary-500 px-4 py-2 text-white no-underline hover:bg-primary-700">
            Gerar QR
        </button>
    </form>

    @if(!empty($qrcode_payload))
        <div class="flex flex-col items-center gap-4">
            <div class="rounded-lg border border-neutral-300 bg-white p-4">
                {!! QrCode::size(260)->generate($qrcode_payload) !!}
            </div>
            <p class="text-sm text-neutral-500" id="tuya-expire-msg">
                Este código expira em <span id="tuya-expire-secs">{{ $expire_time }}</span> segundos.
            </p>
        </div>
    @endif

    @if(!empty($qrcode_payload))
        <script>
            (function () {
                const pollUrl = @json(route('app.tuya.poll', ['token' => $poll_token]));
                const devicesUrl = @json(route('app.tuya.devices'));
                const expireTime = {{ (int) ($expire_time ?? 0) }};
                let remaining = expireTime;
                const span = document.getElementById('tuya-expire-secs');
                const msgEl = document.getElementById('tuya-expire-msg');

                const timer = setInterval(function () {
                    remaining -= 1;
                    if (span) span.textContent = remaining;
                    if (remaining <= 0) {
                        clearInterval(timer);
                        if (msgEl) msgEl.textContent = 'Código expirado. Gere um novo QR.';
                    }
                }, 1000);

                const poll = setInterval(function () {
                    if (remaining <= 0) {
                        clearInterval(poll);
                        return;
                    }
                    fetch(pollUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(function (data) {
                            if (data.linked) {
                                clearInterval(poll);
                                clearInterval(timer);
                                window.location.href = devicesUrl + '?linked=1';
                            }
                        })
                        .catch(function () {});
                }, 3000);
            })();
        </script>
    @endif
</section>
