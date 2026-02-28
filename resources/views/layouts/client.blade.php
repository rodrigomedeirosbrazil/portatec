<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Client</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="font-sans m-0 bg-neutral-100">
    <nav class="flex items-center gap-3 bg-primary-900 px-5 py-3 text-white">
        <a href="{{ route('app.dashboard') }}" class="font-semibold text-white no-underline">Painel</a>
        <a href="{{ route('app.places.index') }}" class="text-white no-underline">Locais</a>
        <a href="{{ route('app.devices.index') }}" class="text-white no-underline">Dispositivos</a>
        <a href="{{ route('app.bookings.index') }}" class="text-white no-underline">Reservas</a>
        <a href="{{ route('app.access-codes.index') }}" class="text-white no-underline">Códigos de acesso</a>
        <a href="{{ route('app.integrations.index') }}" class="text-white no-underline">Integrações</a>
        <a href="/admin" class="text-white no-underline">Admin</a>
        <form method="POST" action="{{ route('logout') }}" class="ml-auto">
            @csrf
            <button type="submit" class="cursor-pointer rounded-md border-0 bg-primary-700 px-3 py-2 text-white hover:bg-primary-900">
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
