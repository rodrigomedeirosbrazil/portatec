<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Client</title>
    @livewireStyles
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; background: #f6f7f9;">
    <nav style="background: #111827; color: #fff; padding: 12px 20px; display: flex; gap: 12px; align-items: center;">
        <a href="{{ route('app.dashboard') }}" style="color: #fff; text-decoration: none; font-weight: 600;">Dashboard</a>
        <a href="{{ route('app.places.index') }}" style="color: #fff; text-decoration: none;">Places</a>
        <a href="{{ route('app.devices.index') }}" style="color: #fff; text-decoration: none;">Devices</a>
        <a href="{{ route('app.bookings.index') }}" style="color: #fff; text-decoration: none;">Bookings</a>
        <a href="{{ route('app.access-codes.index') }}" style="color: #fff; text-decoration: none;">Access Codes</a>
        <a href="{{ route('app.integrations.index') }}" style="color: #fff; text-decoration: none;">Integrations</a>
        <a href="/admin" style="color: #fff; text-decoration: none;">Admin</a>
        <form method="POST" action="{{ route('logout') }}" style="margin-left: auto;">
            @csrf
            <button type="submit" style="background: #374151; color: #fff; border: 0; border-radius: 6px; padding: 8px 12px; cursor: pointer;">
                Sair
            </button>
        </form>
    </nav>

    <main style="max-width: 980px; margin: 24px auto; padding: 0 16px;">
        @if (session('status'))
            <div style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px;">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
