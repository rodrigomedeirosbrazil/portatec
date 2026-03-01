<section>
    <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <h1 class="my-2 mb-4">Editar Dispositivo</h1>

    <form wire:submit="save" class="grid gap-2.5 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <x-place-select
            :places="$places"
            wire:model="placeId"
            label="Local"
            :required="true"
            error-name="placeId"
        />

        <div>
            <label for="name">Nome</label><br>
            <input id="name" type="text" wire:model="name" class="w-full rounded-lg border border-neutral-300 p-2" required>
            @error('name') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="brand">Marca</label><br>
            <select id="brand" wire:model="brand" class="w-full rounded-lg border border-neutral-300 p-2" required>
                @foreach ($brands as $brandOption)
                    <option value="{{ $brandOption->value }}">{{ $brandOption->value }}</option>
                @endforeach
            </select>
            @error('brand') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="external_device_id">ID externo (opcional)</label><br>
            <input id="external_device_id" type="text" wire:model="external_device_id" class="w-full rounded-lg border border-neutral-300 p-2">
            @error('external_device_id') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="default_pin">PIN padrão (opcional, 6 dígitos)</label><br>
            <input id="default_pin" type="text" wire:model="default_pin" class="w-full rounded-lg border border-neutral-300 p-2" maxlength="6" inputmode="numeric" pattern="[0-9]*">
            @error('default_pin') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div class="mt-4 border-t border-neutral-200 pt-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="mt-0 text-lg">Funções</h2>
                <button type="button" wire:click="addFunction" class="cursor-pointer rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm hover:bg-neutral-50">
                    Adicionar função
                </button>
            </div>

            @foreach ($deviceFunctions as $index => $fn)
                <div class="mb-3 flex flex-wrap items-end gap-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3" wire:key="function-{{ $index }}">
                    <div class="min-w-[120px] flex-1">
                        <label for="fn-type-{{ $index }}" class="mb-1 block text-sm font-medium">Tipo</label>
                        <select id="fn-type-{{ $index }}" wire:model="deviceFunctions.{{ $index }}.type" class="w-full rounded-lg border border-neutral-300 p-2">
                            @foreach ($deviceTypes as $typeOption)
                                <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                            @endforeach
                        </select>
                        @error('deviceFunctions.'.$index.'.type') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-[100px] flex-1">
                        <label for="fn-pin-{{ $index }}" class="mb-1 block text-sm font-medium">PIN</label>
                        <input id="fn-pin-{{ $index }}" type="text" wire:model="deviceFunctions.{{ $index }}.pin" class="w-full rounded-lg border border-neutral-300 p-2">
                        @error('deviceFunctions.'.$index.'.pin') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="removeFunction({{ $index }})" class="cursor-pointer rounded-lg border border-error-300 bg-error-50 px-3 py-2 text-sm text-error-700 hover:bg-error-100" @if(count($deviceFunctions) <= 1) disabled @endif>
                        Remover
                    </button>
                </div>
            @endforeach

            @error('deviceFunctions') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
            Atualizar Dispositivo
        </button>
    </form>
</section>
