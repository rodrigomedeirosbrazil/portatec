<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Client</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
</head>
<body class="font-sans m-0 bg-neutral-100">
    <nav x-data="{ open: false }" class="relative border-b border-neutral-200 bg-white shadow-sm">
        <div class="flex items-center justify-between px-5 py-3">
            {{-- Mobile: hamburger + logo --}}
            <div class="flex items-center gap-3 md:hidden">
                <button
                    type="button"
                    @click="open = !open"
                    :aria-expanded="open"
                    :aria-label="open ? 'Fechar menu' : 'Abrir menu'"
                    class="rounded-md p-2 text-neutral-700 hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <a href="{{ route('app.dashboard') }}" class="no-underline">
                    <img src="{{ asset('images/logo/portatec-logo-branco-horizontal.png') }}" alt="Portatec" class="h-8 w-auto">
                </a>
            </div>

            {{-- Desktop: logo + links + Sair --}}
            <div class="hidden md:flex md:items-center md:gap-6">
                <a href="{{ route('app.dashboard') }}" class="no-underline">
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
            </div>
        </div>

        {{-- Mobile dropdown menu --}}
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            @click.away="open = false"
            class="absolute left-0 right-0 top-full z-50 border-b border-neutral-200 bg-white shadow-lg md:hidden"
        >
            <div class="flex flex-col gap-1 px-5 py-3">
                <a href="{{ route('app.dashboard') }}" class="py-2 text-neutral-700 no-underline hover:text-primary-700">Dashboard</a>
                <a href="{{ route('app.places.index') }}" class="py-2 text-neutral-700 no-underline hover:text-primary-700">Locais</a>
                <a href="{{ route('app.devices.index') }}" class="py-2 text-neutral-700 no-underline hover:text-primary-700">Dispositivos</a>
                <a href="{{ route('app.bookings.index') }}" class="py-2 text-neutral-700 no-underline hover:text-primary-700">Reservas</a>
                <a href="{{ route('app.access-codes.index') }}" class="py-2 text-neutral-700 no-underline hover:text-primary-700">Códigos de acesso</a>
                <a href="{{ route('app.integrations.index') }}" class="py-2 text-neutral-700 no-underline hover:text-primary-700">Integrações</a>
                <a href="/admin" class="py-2 text-neutral-700 no-underline hover:text-primary-700">Admin</a>
                <form method="POST" action="{{ route('logout') }}" class="pt-2">
                    @csrf
                    <button type="submit" class="w-full cursor-pointer rounded-md border-0 bg-primary-500 px-3 py-2 text-left text-white hover:bg-primary-700">
                        Sair
                    </button>
                </form>
            </div>
        </div>
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
