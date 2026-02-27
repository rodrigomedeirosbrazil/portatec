<section>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h1 style="margin: 0;">Places</h1>
        <a href="{{ route('app.places.create') }}" style="background: #111827; color: #fff; text-decoration: none; border-radius: 8px; padding: 8px 12px;">
            Novo Place
        </a>
    </div>

    <div style="display: grid; gap: 12px;">
        @forelse ($places as $place)
            <article style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px;">
                <h2 style="margin: 0 0 8px; font-size: 18px;">
                    <a href="{{ route('app.places.show', $place->id) }}" style="color: #111827; text-decoration: none;">
                        {{ $place->name }}
                    </a>
                </h2>
                <p style="margin: 0; color: #4b5563;">
                    Devices: {{ $place->devices_count }} | Bookings: {{ $place->bookings_count }} | Access Codes: {{ $place->access_codes_count }}
                </p>
            </article>
        @empty
            <p style="color: #4b5563;">Você ainda não possui places.</p>
        @endforelse
    </div>
</section>
