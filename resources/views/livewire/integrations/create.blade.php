<section>
    <a href="{{ route('app.integrations.index') }}" style="color: #2563eb; text-decoration: none;">&larr; Voltar</a>
    <h1 style="margin: 8px 0 16px;">Nova Integração iCal</h1>

    <form wire:submit="save" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; display: grid; gap: 10px;">
        <div>
            <label for="platformId">Plataforma</label><br>
            <select id="platformId" wire:model="platformId" style="padding: 8px; width: 100%;">
                @foreach ($platforms as $platform)
                    <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="placeId">Place</label><br>
            <select id="placeId" wire:model="placeId" style="padding: 8px; width: 100%;">
                @foreach ($places as $place)
                    <option value="{{ $place->id }}">{{ $place->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="externalId">URL iCal</label><br>
            <input id="externalId" type="url" wire:model="externalId" style="padding: 8px; width: 100%;">
        </div>

        <button type="submit" style="background: #111827; color: #fff; border: 0; border-radius: 8px; padding: 8px 12px; cursor: pointer;">
            Salvar Integração
        </button>
    </form>
</section>
