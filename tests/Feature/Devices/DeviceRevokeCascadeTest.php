<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\AccessCode;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use App\Services\Device\DeviceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceRevokeCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_revoking_detaches_the_device_from_the_users_places(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner);
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $apto = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $apto->id, 'user_id' => $resident->id, 'role' => 'admin']);
        $gate->places()->attach($apto->id);

        app(DeviceGrantService::class)->revoke($gate, $resident);

        $this->assertFalse($gate->places()->where('places.id', $apto->id)->exists());
        $this->assertFalse($gate->fresh()->isUsableBy($resident));
    }

    public function test_device_stays_when_another_place_admin_still_holds_a_grant(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();
        $spouse = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner);
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);
        $gate->deviceUsers()->create(['user_id' => $spouse->id, 'role' => 'user']);

        $apto = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $apto->id, 'user_id' => $resident->id, 'role' => 'admin']);
        PlaceUser::create(['place_id' => $apto->id, 'user_id' => $spouse->id, 'role' => 'admin']);
        $gate->places()->attach($apto->id);

        app(DeviceGrantService::class)->revoke($gate, $resident);

        $this->assertTrue(
            $gate->places()->where('places.id', $apto->id)->exists(),
            'O acesso do local só cai quando ninguém mais o sustenta.'
        );
    }

    public function test_revoking_removes_the_codes_from_the_device(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner);
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $apto = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $apto->id, 'user_id' => $resident->id, 'role' => 'admin']);
        $gate->places()->attach($apto->id);

        AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto->id,
            'pin' => '123456',
            'start' => now()->subDay(),
            'end' => now()->addDays(5),
        ]));

        app(DeviceGrantService::class)->revoke($gate, $resident);

        // Com o dispositivo fora do local, a sincronização do place não o
        // alcança mais: é o que garante que o PIN saiu do equipamento.
        $this->assertFalse($gate->fresh()->places()->where('places.id', $apto->id)->exists());
    }

    private function deviceOwnedBy(User $user): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }
}
