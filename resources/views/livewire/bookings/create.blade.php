<section>
    <a href="{{ route('app.bookings.index') }}" style="color: #2563eb; text-decoration: none;">&larr; Voltar</a>
    <h1 style="margin: 8px 0 16px;">Novo Booking</h1>

    <form wire:submit="save" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; display: grid; gap: 10px;">
        <div>
            <label for="placeId">Place</label><br>
            <select id="placeId" wire:model="placeId" style="padding: 8px; width: 100%;">
                @foreach ($places as $place)
                    <option value="{{ $place->id }}">{{ $place->name }}</option>
                @endforeach
            </select>
            @error('placeId') <p style="color: #dc2626; margin: 4px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="guestName">Hóspede</label><br>
            <input id="guestName" type="text" wire:model="guestName" style="padding: 8px; width: 100%;">
            @error('guestName') <p style="color: #dc2626; margin: 4px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="checkIn">Check-in</label><br>
            <input id="checkIn" type="datetime-local" wire:model="checkIn" style="padding: 8px; width: 100%;">
            @error('checkIn') <p style="color: #dc2626; margin: 4px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="checkOut">Check-out</label><br>
            <input id="checkOut" type="datetime-local" wire:model="checkOut" style="padding: 8px; width: 100%;">
            @error('checkOut') <p style="color: #dc2626; margin: 4px 0 0;">{{ $message }}</p> @enderror
        </div>

        <button type="submit" style="background: #111827; color: #fff; border: 0; border-radius: 8px; padding: 8px 12px; cursor: pointer;">
            Salvar Booking
        </button>
    </form>
</section>
