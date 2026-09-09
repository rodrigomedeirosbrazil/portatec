<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\AccessCode;
use App\Models\AccessEvent;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_device_admin_sees_codes_without_the_digits(): void
    {
        $owner = User::factory()->create();
        $gate = $this->deviceOwnedBy($owner);

        $apto = Place::create(['name' => 'Apto 1']);
        $gate->places()->attach($apto->id);

        AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto->id,
            'pin' => '654321',
            'start' => now()->subDay(),
            'end' => now()->addDays(5),
        ]));

        $this->actingAs($owner)
            ->get("/app/devices/{$gate->id}")
            ->assertOk()
            ->assertSee('Apto 1')
            ->assertDontSee('654321');
    }

    public function test_resident_does_not_see_events_from_another_place(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner);

        $apto1 = Place::create(['name' => 'Apto 1']);
        $apto2 = Place::create(['name' => 'Apto 2']);
        PlaceUser::create(['place_id' => $apto1->id, 'user_id' => $resident->id, 'role' => 'admin']);
        $gate->places()->attach([$apto1->id, $apto2->id]);

        $mine = AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto1->id, 'pin' => '111111', 'start' => now()->subDay(), 'end' => now()->addDay(),
        ]));
        $theirs = AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto2->id, 'pin' => '222222', 'start' => now()->subDay(), 'end' => now()->addDay(),
        ]));

        $mineEvent = AccessEvent::create([
            'device_id' => $gate->id, 'access_code_id' => $mine->id, 'pin' => '111111', 'result' => 'success',
        ]);
        $theirsEvent = AccessEvent::create([
            'device_id' => $gate->id, 'access_code_id' => $theirs->id, 'pin' => '222222', 'result' => 'success',
        ]);

        $this->assertTrue($resident->can('view', $mineEvent));
        $this->assertFalse($resident->can('view', $theirsEvent));
    }

    public function test_device_admin_sees_every_event(): void
    {
        $owner = User::factory()->create();
        $gate = $this->deviceOwnedBy($owner);

        $apto = Place::create(['name' => 'Apto 1']);
        $gate->places()->attach($apto->id);

        $event = AccessEvent::create([
            'device_id' => $gate->id, 'access_code_id' => null, 'pin' => '999999', 'result' => 'invalid',
        ]);

        $this->assertTrue($owner->can('view', $event));
    }

    private function deviceOwnedBy(User $user): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Pedestres 1',
            'brand' => 'portatec',
            'external_device_id' => 'chip-ped',
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }
}
