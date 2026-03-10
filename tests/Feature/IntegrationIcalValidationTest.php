<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Integrations\Create as CreateIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationIcalValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_airbnb_integration_rejects_reservation_details_url(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Integration User',
            'email' => 'integration@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'name' => 'Airbnb',
            'slug' => 'airbnb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'Integration Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('place_users')->insert([
            'place_id' => $placeId,
            'user_id' => $userId,
            'role' => 'host',
            'label' => 'Host',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::find($userId));

        Livewire::test(CreateIntegration::class)
            ->set('platformId', $platformId)
            ->set('placeId', $placeId)
            ->set('externalId', 'https://www.airbnb.com/hosting/reservations/details/HM2AHARRC4')
            ->call('save')
            ->assertHasErrors(['externalId']);
    }

    public function test_airbnb_integration_accepts_ics_url(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Integration User',
            'email' => 'integration2@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'name' => 'Airbnb',
            'slug' => 'airbnb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'Integration Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('place_users')->insert([
            'place_id' => $placeId,
            'user_id' => $userId,
            'role' => 'host',
            'label' => 'Host',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::find($userId));

        Livewire::test(CreateIntegration::class)
            ->set('platformId', $platformId)
            ->set('placeId', $placeId)
            ->set('externalId', 'https://example.test/calendar.ics')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('place_integration', [
            'place_id' => $placeId,
            'external_id' => 'https://example.test/calendar.ics',
        ]);
    }
}
