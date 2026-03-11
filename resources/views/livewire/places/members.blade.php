<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.places.show', $place->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">{{ __('app.manage_members') }} – {{ $place->name }}</h1>
        </div>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">{{ session('error') }}</p>
    @endif

    <div class="mb-6 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <h2 class="mt-0 mb-3">{{ __('app.members') }}</h2>
        <ul class="m-0 list-none pl-0">
            @forelse ($placeUsers as $placeUser)
                <li class="flex items-center justify-between gap-2 border-b border-neutral-200 py-2 last:border-b-0">
                    <div>
                        <strong>{{ $placeUser->user->name }}</strong>
                        <span class="text-neutral-500">({{ $placeUser->user->email }})</span>
                        — {{ $placeRoles[$placeUser->role] ?? $placeUser->role }}
                        @if ($placeUser->label)
                            — {{ $placeUser->label }}
                        @endif
                    </div>
                    <button
                        type="button"
                        wire:click="removeMember({{ $placeUser->id }})"
                        wire:confirm="Tem certeza que deseja remover este membro?"
                        class="rounded-lg border border-red-200 bg-white px-2 py-1 text-sm text-red-700 hover:bg-red-50"
                    >
                        Remover
                    </button>
                </li>
            @empty
                <li class="text-neutral-500">Nenhum membro além de você.</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <h2 class="mt-0 mb-3">{{ __('app.add_member') }}</h2>
        <form wire:submit="addMember" class="space-y-3">
            <div>
                <label for="userSearch" class="mb-1 block text-sm font-medium">Buscar usuário (nome ou e-mail)</label>
                <input
                    id="userSearch"
                    type="text"
                    wire:model.live.debounce.300ms="userSearch"
                    class="w-full rounded-lg border border-neutral-300 p-2.5"
                    placeholder="Digite para buscar..."
                />
            </div>
            <div>
                <label for="addUserId" class="mb-1 block text-sm font-medium">{{ __('app.user') }}</label>
                <select
                    id="addUserId"
                    wire:model="addUserId"
                    class="w-full rounded-lg border border-neutral-300 p-2.5"
                    required
                >
                    <option value="">Selecione um usuário</option>
                    @foreach ($usersNotInPlace as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
                @error('addUserId')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="addRole" class="mb-1 block text-sm font-medium">{{ __('app.role') }}</label>
                <select id="addRole" wire:model="addRole" class="w-full rounded-lg border border-neutral-300 p-2.5">
                    @foreach ($placeRoles as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="addLabel" class="mb-1 block text-sm font-medium">{{ __('app.label') }}</label>
                <input
                    id="addLabel"
                    type="text"
                    wire:model="addLabel"
                    class="w-full rounded-lg border border-neutral-300 p-2.5"
                    maxlength="255"
                    placeholder="Opcional"
                />
            </div>
            <button
                type="submit"
                class="rounded-lg bg-primary-500 px-3 py-2 text-white hover:bg-primary-700"
            >
                Adicionar
            </button>
        </form>
    </div>
</section>
