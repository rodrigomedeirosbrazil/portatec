<section>
    <a href="{{ route('app.access-codes.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <h1 class="my-2 mb-4">Novo Access Code</h1>

    <form wire:submit="save" class="grid gap-2.5 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <x-place-select
            :places="$places"
            wire:model="placeId"
            label="Local"
            :required="true"
            error-name="placeId"
        />

        <div>
            <label for="pin">PIN (opcional)</label><br>
            <input id="pin" type="text" wire:model="pin" class="w-full p-2">
        </div>

        <div>
            <label for="start">Início</label><br>
            <input id="start" type="datetime-local" wire:model="start" class="w-full p-2">
        </div>

        <div>
            <label for="end">Fim (opcional)</label><br>
            <input id="end" type="datetime-local" wire:model="end" class="w-full p-2">
        </div>

        <button type="submit" class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
            Salvar PIN
        </button>
    </form>
</section>
