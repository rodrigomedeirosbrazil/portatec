<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Enums\DeviceBrandEnum;
use App\Enums\PlaceRoleEnum;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuyaLockStatusScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_control_sends_the_lock_status(): void
    {
        [$user, , $lock] = $this->scenario();

        $this->actingAs($user)
            ->get("/app/devices/{$lock->id}/control")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('device.lock_status.locked', true)
                ->where('device.lock_status.battery', 51)
                ->where('device.supports_tuya_temporary_password', false));
    }

    public function test_place_control_sends_the_lock_status_only_for_locks(): void
    {
        [$user, $place, $lock] = $this->scenario();
        $other = Device::create(['name' => 'Portão', 'brand' => DeviceBrandEnum::Portatec]);
        $other->places()->attach($place->id);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}/control")
            ->assertOk()
            ->assertInertia(function ($page) use ($lock): void {
                $devices = collect($page->toArray()['props']['devices'])->keyBy('id');

                $this->assertTrue($devices[$lock->id]['lock_status']['locked']);
                $this->assertNull($devices->firstWhere('name', 'Portão')['lock_status']);
            });
    }

    public function test_device_show_sends_the_lock_status_and_a_place_to_listen_on(): void
    {
        [$user, $place, $lock] = $this->scenario();

        $this->actingAs($user)
            ->get("/app/devices/{$lock->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('device.lock_status.locked', true)
                ->where('placeId', $place->id));
    }

    public function test_device_show_sends_no_place_when_the_user_is_not_a_member_of_any_place(): void
    {
        [, , $lock] = $this->scenario();

        // Admin só do equipamento: enxerga o local, mas não pode ouvir o canal dele.
        $deviceAdmin = User::factory()->create();
        $lock->deviceUsers()->create(['user_id' => $deviceAdmin->id, 'role' => 'admin']);

        $this->actingAs($deviceAdmin)
            ->get("/app/devices/{$lock->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('device.lock_status.locked', true)
                ->where('placeId', null));
    }

    /** @return array{0: User, 1: Place, 2: Device} */
    private function scenario(): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa da Praia']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => PlaceRoleEnum::Admin,
            'label' => $user->name,
        ]);

        // Sem integration_id: o refresh de snapshot das telas sai cedo, sem HTTP.
        $lock = Device::create([
            'name' => 'Smart Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'ms',
            'external_device_id' => 'dev-1',
            'tuya_status_payload' => [
                ['code' => 'lock_motor_state', 'value' => false, 't' => null],
                ['code' => 'residual_electricity', 'value' => 51, 't' => null],
            ],
        ]);
        $lock->places()->attach($place->id);

        return [$user, $place, $lock];
    }
}
