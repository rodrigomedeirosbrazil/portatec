<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlaceUsersIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_user_only_sees_bookings_from_places_he_belongs_to(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedPlaceId = DB::table('places')->insertGetId([
            'name' => 'Owned Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $foreignPlaceId = DB::table('places')->insertGetId([
            'name' => 'Foreign Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('place_users')->insert([
            [
                'place_id' => $ownedPlaceId,
                'user_id' => $user->id,
                'role' => 'admin',
                'label' => 'Owner',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'place_id' => $foreignPlaceId,
                'user_id' => $otherUser->id,
                'role' => 'admin',
                'label' => 'Owner',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Booking::create([
            'place_id' => $ownedPlaceId,
            'guest_name' => 'Guest Owned',
            'check_in' => now()->addDay(),
            'check_out' => now()->addDays(2),
            'source' => 'manual',
        ]);

        Booking::create([
            'place_id' => $foreignPlaceId,
            'guest_name' => 'Guest Foreign',
            'check_in' => now()->addDays(3),
            'check_out' => now()->addDays(4),
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->get('/app/bookings')
            ->assertOk()
            ->assertSee('Guest Owned')
            ->assertDontSee('Guest Foreign');
    }
}
