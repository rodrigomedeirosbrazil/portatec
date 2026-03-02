<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Client</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
</head>
<body class="font-sans m-0 bg-neutral-100">
    <nav class="flex items-center gap-6 border-b border-neutral-200 bg-white px-5 py-3 shadow-sm">
        <a href="{{ route('app.dashboard') }}" class="flex items-center gap-6 no-underline">
            <img src="{{ asset('images/logo/portatec-logo-branco-horizontal.png') }}" alt="Portatec" class="h-8 w-auto">
        </a>
        <a href="{{ route('app.places.index') }}" class="text-neutral-700 no-underline hover:text-primary-700">Locais</a>
        <a href="{{ route('app.devices.index') }}" class="text-neutral-700 no-underline hover:text-primary-700">Dispositivos</a>
        <a href="{{ route('app.bookings.index') }}" class="text-neutral-700 no-underline hover:text-primary-700">Reservas</a>
        <a href="{{ route('app.access-codes.index') }}" class="text-neutral-700 no-underline hover:text-primary-700">Códigos de acesso</a>
        <a href="{{ route('app.integrations.index') }}" class="text-neutral-700 no-underline hover:text-primary-700">Integrações</a>
        <a href="/admin" class="text-neutral-700 no-underline hover:text-primary-700">Admin</a>
        <form method="POST" action="{{ route('logout') }}" class="ml-auto">
            @csrf
            <button type="submit" class="cursor-pointer rounded-md border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
                Sair
            </button>
        </form>
    </nav>

    <main class="mx-auto max-w-[980px] px-4 py-6">
        @if (session()->has('impersonator_id'))
            <div class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-amber-800">
                <span>Voce esta em sessao assumida.</span>
                <form method="POST" action="{{ route('app.impersonations.stop') }}">
                    @csrf
                    <button type="submit" class="cursor-pointer rounded-md border-0 bg-amber-800 px-3 py-2 text-white">
                        Finalizar sessao assumida
                    </button>
                </form>
            </div>
        @endif

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-success-300 bg-success-100 px-3 py-2.5 text-success-700">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
