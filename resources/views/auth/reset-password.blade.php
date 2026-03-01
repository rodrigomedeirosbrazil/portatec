<x-layouts.guest subtitle="Defina uma nova senha para sua conta">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autofocus value="{{ old('email', $request->email) }}" class="w-full rounded-md border border-neutral-300 px-3 py-2 outline-none focus:border-primary-500">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Nova senha</label>
            <input id="password" name="password" type="password" required class="w-full rounded-md border border-neutral-300 px-3 py-2 outline-none focus:border-primary-500">
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirmar nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-md border border-neutral-300 px-3 py-2 outline-none focus:border-primary-500">
        </div>

        <button type="submit" class="w-full rounded-md bg-primary-500 hover:bg-primary-700 px-4 py-2 text-sm font-medium text-white">
            Redefinir senha
        </button>
    </form>
</x-layouts.guest>
