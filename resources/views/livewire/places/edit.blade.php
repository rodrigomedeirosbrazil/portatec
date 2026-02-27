<section>
    <a href="{{ route('app.places.show', $place->id) }}" style="color: #2563eb; text-decoration: none;">&larr; Voltar</a>
    <h1 style="margin: 8px 0 16px;">Editar Place</h1>

    <form wire:submit="save" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
        <label for="name" style="display: block; margin-bottom: 6px;">Nome</label>
        <input id="name" type="text" wire:model="name" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
        @error('name')
            <p style="color: #dc2626; margin: 6px 0 0;">{{ $message }}</p>
        @enderror

        <button type="submit" style="margin-top: 12px; background: #111827; color: #fff; border: 0; border-radius: 8px; padding: 8px 12px; cursor: pointer;">
            Atualizar
        </button>
    </form>
</section>
