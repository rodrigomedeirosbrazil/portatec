<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Portatec') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-900">
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6 py-10">
        <section class="w-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-neutral-300">
            <img src="{{ asset('images/logo/portatec-logo-branco-horizontal.png') }}" alt="Portatec" class="mb-4 h-10 w-auto">
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
