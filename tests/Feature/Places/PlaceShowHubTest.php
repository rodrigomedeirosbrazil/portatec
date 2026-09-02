<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Models\Booking;
use App\Models\Integration;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlaceShowHubTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlace(): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa Azul']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return [$user, $place];
    }

    /**
     * Regressão: o tile usava `place.bookings.length`, e o controller carregava
     * `bookings` com `limit(10)`. A contagem travava em 10 para sempre.
     */
    public function test_booking_count_is_not_capped_at_ten(): void
    {
        [$user, $place] = $this->userWithPlace();

        Booking::factory()->count(13)->create(['place_id' => $place->id]);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('places/show')
                ->where('bookingsCount', 13)
            );
    }

    public function test_place_lists_its_ical_booking_sources(): void
    {
        [$user, $place] = $this->userWithPlace();

        $airbnb = Platform::create(['name' => 'Airbnb', 'slug' => 'airbnb']);
        $ical = Integration::create(['platform_id' => $airbnb->id, 'user_id' => $user->id]);
        $ical->places()->attach($place->id, ['external_id' => 'ext-airbnb-1']);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('bookingSources', 1)
                ->where('bookingSources.0.id', $ical->id)
            );
    }

    /**
     * A integração Tuya não tem local e não é fonte de reserva. Ela divide a
     * tabela `integrations` com as de iCal, separadas só por `platform.slug`.
     */
    public function test_tuya_integration_is_not_listed_as_a_booking_source(): void
    {
        [$user, $place] = $this->userWithPlace();

        $tuya = Platform::create(['name' => 'Tuya', 'slug' => 'tuya']);
        $tuyaIntegration = Integration::create(['platform_id' => $tuya->id, 'user_id' => $user->id]);
        $tuyaIntegration->places()->attach($place->id, ['external_id' => 'ext-tuya-1']);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('bookingSources', 0));
    }
}
