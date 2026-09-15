<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon-96x96.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    {{-- Conexão do Reverb, entregue pelo servidor em runtime. Antes vinha de
         import.meta.env, o que obrigava a reconstruir o bundle no arranque do
         container para embutir a URL pública — 17 s a cada deploy e o Node
         inteiro dentro da imagem. Precisa vir ANTES do @vite: o echo.js é
         importado no topo do app.tsx e constrói o Echo no momento do import. --}}
    <script>
        window.__reverb = @json(array_merge(config('reverb_client'), [
            'host' => config('reverb_client.host') ?: request()->getHost(),
        ]));
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="font-sans m-0 bg-neutral-100">
    @inertia
</body>
</html>
