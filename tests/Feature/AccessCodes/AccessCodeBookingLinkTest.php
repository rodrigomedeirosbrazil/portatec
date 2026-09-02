<?php

declare(strict_types=1);

namespace Tests\Feature\AccessCodes;

use App\Models\AccessCode;
use App\Models\Booking;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * As duas telas passam a navegar uma para a outra. O par só é montável se as
 * duas pontas do vínculo chegarem nas props.
 */
class AccessCodeBookingLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_code_list_exposes_the_origin_booking(): void
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa Azul']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        $booking = Booking::factory()->create(['place_id' => $place->id]);
        $code = AccessCode::where('booking_id', $booking->id)->first()
            ?? AccessCode::create([
                'place_id' => $place->id,
                'user_id' => $user->id,
                'booking_id' => $booking->id,
                'pin' => '654321',
                'start' => now()->subDay(),
                'end' => now()->addDay(),
            ]);

        $this->actingAs($user)
            ->get('/app/access-codes')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('access-codes/index')
                ->where('accessCodes.data.0.booking_id', $booking->id)
            );

        $this->actingAs($user)
            ->get("/app/bookings/{$booking->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('bookings/show')
                ->where('booking.access_code.id', $code->id)
            );
    }
}
