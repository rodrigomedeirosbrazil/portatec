<section>
    <div class="mb-4">
        <a href="{{ route('app.places.show', $place->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
        <h1 class="m-0 mt-2">{{ __('app.clone_place') }}: {{ $place->name }}</h1>
    </div>

    <form wire:submit="clonePlace" class="space-y-6">
        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <label for="name" class="mb-1 block font-medium">{{ __('app.clone_place_new_name') }}</label>
            <input
                id="name"
                type="text"
                wire:model="name"
                class="w-full rounded-lg border border-neutral-300 p-2.5"
                maxlength="255"
                required
            />
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="mt-0">{{ __('app.clone_place_add_people') }}</h2>
                <button
                    type="button"
                    wire:click="addMemberRow"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-50"
                >
                    + Adicionar pessoa
                </button>
            </div>
            <p class="mb-3 text-sm text-neutral-500">Você será administrador do novo local. Opcionalmente adicione outras pessoas.</p>

            @foreach ($additionalMembers as $index => $member)
                <div class="mb-3 flex flex-wrap items-end gap-2 rounded border border-neutral-200 p-2" wire:key="member-{{ $index }}">
                    <div class="min-w-[200px] flex-1">
                        <label class="mb-1 block text-sm">{{ __('app.user') }}</label>
                        <select
                            wire:model="additionalMembers.{{ $index }}.user_id"
                            class="w-full rounded-lg border border-neutral-300 p-2 text-sm"
                        >
                            <option value="">Selecione</option>
                            @foreach ($usersForSelect as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        @error('additionalMembers.'.$index.'.user_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="w-32">
                        <label class="mb-1 block text-sm">{{ __('app.role') }}</label>
                        <select
                            wire:model="additionalMembers.{{ $index }}.role"
                            class="w-full rounded-lg border border-neutral-300 p-2 text-sm"
                        >
                            @foreach ($placeRoles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[120px] flex-1">
                        <label class="mb-1 block text-sm">{{ __('app.label') }}</label>
                        <input
                            type="text"
                            wire:model="additionalMembers.{{ $index }}.label"
                            class="w-full rounded-lg border border-neutral-300 p-2 text-sm"
                            placeholder="Opcional"
                        />
                    </div>
                    <button
                        type="button"
                        wire:click="removeMemberRow({{ $index }})"
                        class="rounded border border-red-200 bg-white px-2 py-2 text-sm text-red-600 hover:bg-red-50"
                    >
                        Remover
                    </button>
                </div>
            @endforeach
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-primary-500 px-4 py-2 text-white hover:bg-primary-700">
                {{ __('app.clone_place') }}
            </button>
            <a href="{{ route('app.places.show', $place->id) }}" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-neutral-700 no-underline hover:bg-neutral-50">
                Cancelar
            </a>
        </div>
    </form>
</section>
