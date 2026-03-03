<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Portatec') }}</title>
    @vite(['resources/css/app.css'])
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon-96x96.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-900">
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6 py-10">
        <section class="w-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-neutral-300">
            <div class="mb-6 flex justify-center">
                <img src="{{ asset('images/logo/portatec-logo-branco.png') }}" alt="Portatec" class="h-28 w-auto max-w-[280px]">
            </div>
            <p class="mb-6 text-sm text-neutral-500">{{ $subtitle ?? 'Acesso ao painel do cliente' }}</p>

            @if (session('status'))
                <div class="mb-4 rounded-md border border-success-300 bg-success-100 px-3 py-2 text-sm text-success-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-error-300 bg-error-100 px-3 py-2 text-sm text-error-700">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </section>
    </main>
</body>
</html>
