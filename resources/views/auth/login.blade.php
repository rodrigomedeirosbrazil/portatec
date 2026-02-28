<x-layouts.guest subtitle="Faça login para acessar o painel do cliente">
    <form method="POST" action="{{ url('/app/login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}" class="w-full rounded-md border border-neutral-300 px-3 py-2 outline-none focus:border-primary-500">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Senha</label>
            <input id="password" name="password" type="password" required class="w-full rounded-md border border-neutral-300 px-3 py-2 outline-none focus:border-primary-500">
        </div>

        <label class="flex items-center gap-2 text-sm text-neutral-700">
            <input type="checkbox" name="remember" class="rounded border-neutral-300">
            Lembrar de mim
        </label>

        <button type="submit" class="w-full rounded-md bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
            Entrar
        </button>
    </form>

    <div class="mt-5 flex items-center justify-between text-sm">
        <a href="{{ route('password.request') }}" class="text-neutral-700 underline">Esqueci minha senha</a>
        <a href="{{ route('register') }}" class="text-neutral-700 underline">Criar conta</a>
    </div>
</x-layouts.guest>
