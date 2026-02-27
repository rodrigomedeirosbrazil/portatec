<section>
    <a href="{{ route('app.access-codes.index') }}" style="color: #2563eb; text-decoration: none;">&larr; Voltar</a>
    <h1 style="margin: 8px 0 16px;">Novo Access Code</h1>

    <form wire:submit="save" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; display: grid; gap: 10px;">
        <div>
            <label for="placeId">Place</label><br>
            <select id="placeId" wire:model="placeId" style="padding: 8px; width: 100%;">
                @foreach ($places as $place)
                    <option value="{{ $place->id }}">{{ $place->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="pin">PIN (opcional)</label><br>
            <input id="pin" type="text" wire:model="pin" style="padding: 8px; width: 100%;">
        </div>

        <div>
            <label for="start">Início</label><br>
            <input id="start" type="datetime-local" wire:model="start" style="padding: 8px; width: 100%;">
        </div>

        <div>
            <label for="end">Fim (opcional)</label><br>
            <input id="end" type="datetime-local" wire:model="end" style="padding: 8px; width: 100%;">
        </div>

        <button type="submit" style="background: #111827; color: #fff; border: 0; border-radius: 8px; padding: 8px 12px; cursor: pointer;">
            Salvar PIN
        </button>
    </form>
</section>
