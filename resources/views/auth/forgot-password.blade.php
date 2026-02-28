<x-layouts.guest subtitle="Informe seu e-mail para receber o link de redefinição">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}" class="w-full rounded-md border border-neutral-300 px-3 py-2 outline-none focus:border-primary-500">
        </div>

        <button type="submit" class="w-full rounded-md bg-primary-500 hover:bg-primary-700 px-4 py-2 text-sm font-medium text-white">
            Enviar link
        </button>
    </form>

    <div class="mt-5 text-sm">
        <a href="{{ route('login') }}" class="text-neutral-700 underline">Voltar ao login</a>
    </div>
</x-layouts.guest>
