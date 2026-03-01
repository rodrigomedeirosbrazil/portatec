<section>
    <a href="{{ route('app.places.show', $place->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <h1 class="my-2 mb-4">Editar Place</h1>

    <form wire:submit="save" class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <label for="name" class="mb-1.5 block">Nome</label>
        <input id="name" type="text" wire:model="name" class="w-full rounded-lg border border-neutral-300 p-2.5">
        @error('name')
            <p class="mt-1.5 text-error-500">{{ $message }}</p>
        @enderror

        <button type="submit" class="mt-3 cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
            Atualizar
        </button>
    </form>
</section>
