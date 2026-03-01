<section>
    <a href="{{ route('app.bookings.index') }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
    <h1 class="my-2 mb-4">Novo Booking</h1>

    <form wire:submit="save" class="grid gap-2.5 rounded-[10px] border border-neutral-300 bg-white p-3.5">
        <x-place-select
            :places="$places"
            wire:model="placeId"
            label="Local"
            :required="true"
            error-name="placeId"
        />

        <div>
            <label for="guestName">Hóspede</label><br>
            <input id="guestName" type="text" wire:model="guestName" class="w-full p-2">
            @error('guestName') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="checkIn">Check-in</label><br>
            <input id="checkIn" type="datetime-local" wire:model="checkIn" class="w-full p-2">
            @error('checkIn') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="checkOut">Check-out</label><br>
            <input id="checkOut" type="datetime-local" wire:model="checkOut" class="w-full p-2">
            @error('checkOut') <p class="mt-1 text-error-500">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="cursor-pointer rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700">
            Salvar Booking
        </button>
    </form>
</section>
