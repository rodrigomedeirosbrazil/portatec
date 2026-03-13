<section>
    <h1 class="mb-4">Vincular conta Tuya (Smart Life)</h1>
    @if(isset($error))
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-amber-800">
            {{ $error }}
        </div>
        <p>
            <a href="{{ route('app.tuya.connect') }}" class="rounded-lg bg-primary-500 px-4 py-2 text-white no-underline hover:bg-primary-700">
                Tentar novamente
            </a>
        </p>
    @else
        <p class="mb-4 text-neutral-600">
            Abra o app Smart Life no celular, vá em <strong>Perfil</strong> e toque no ícone de QR no canto superior direito. Em seguida, escaneie o código abaixo.
        </p>
        @if(session('status'))
            <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-amber-800">
                {{ session('status') }}
            </div>
        @endif
        <div class="flex flex-col items-center gap-4">
            <div class="rounded-lg border border-neutral-300 bg-white p-4">
                {!! QrCode::size(260)->generate($qrcode_url) !!}
            </div>
            <p class="text-sm text-neutral-500" id="tuya-expire-msg">
                Este código expira em <span id="tuya-expire-secs">{{ $expire_time }}</span> segundos.
            </p>
        </div>
    @endif
    @if(!isset($error))
    <script>
        (function () {
            const pollUrl = @json(route('app.tuya.poll', ['token' => $poll_token]));
            const devicesUrl = @json(route('app.tuya.devices'));
            const expireTime = {{ $expire_time }};
            let remaining = expireTime;
            const span = document.getElementById('tuya-expire-secs');
            const msgEl = document.getElementById('tuya-expire-msg');

            const timer = setInterval(function () {
                remaining -= 1;
                if (span) span.textContent = remaining;
                if (remaining <= 0) {
                    clearInterval(timer);
                    if (msgEl) msgEl.textContent = 'Código expirado. Atualize a página para gerar um novo.';
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
