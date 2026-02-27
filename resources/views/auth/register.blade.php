<x-layouts.guest subtitle="Crie sua conta para acessar o painel do cliente">
    <form method="POST" action="{{ url('/app/register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Nome</label>
            <input id="name" name="name" type="text" required autofocus value="{{ old('name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-slate-500">
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required value="{{ old('email') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-slate-500">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Senha</label>
            <input id="password" name="password" type="password" required class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-slate-500">
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirmar senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-slate-500">
        </div>

        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">
            Criar conta
        </button>
    </form>

    <div class="mt-5 text-sm">
        <a href="{{ route('login') }}" class="text-slate-700 underline">Já tenho conta</a>
    </div>
</x-layouts.guest>
