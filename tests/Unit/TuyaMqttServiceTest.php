<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Events\PlaceDeviceStatusEvent;
use App\Events\PlaceTuyaLockStatusEvent;
use App\Models\Device;
use App\Models\Integration;
use App\Models\Place;
use App\Models\Platform;
use App\Models\User;
use App\Services\Tuya\TuyaMqttService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaMqttServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O template do tópico do home usa {ownerId}, que é o ownerId do home devolvido por
     * /v1.0/m/life/users/homes — e NÃO o uid do usuário. Assinar com o uid resulta num
     * tópico válido que nunca recebe mensagem.
     */
    public function test_it_builds_the_home_topic_from_the_home_owner_id_not_the_user_uid(): void
    {
        $integration = $this->integration();

        Http::fake(['apigw.tuyaus.com/*' => Http::response([
            'success' => true,
            'result' => [['ownerId' => 239728351, 'name' => 'Beach house']],
        ])]);

        $topics = (new TuyaMqttService)->topicsFor($integration, [
            'topic' => ['ownerId' => ['sub' => 'cloud/group/{ownerId}/in']],
        ]);

        $this->assertSame(['cloud/group/239728351/in'], $topics);
        $this->assertStringNotContainsString('az-user-uid', $topics[0]);
    }

    public function test_it_builds_both_device_topic_variants_for_each_imported_device(): void
    {
        $integration = $this->integration();

        Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-abc',
            'integration_id' => $integration->id,
        ]);

        Http::fake(['apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => []])]);

        $topics = (new TuyaMqttService)->topicsFor($integration, [
            'topic' => ['devId' => ['sub' => 'cloud/device/{devId}/in/hash123']],
        ]);

        $this->assertSame([
            'cloud/device/dev-abc/in/hash123/pen',
            'cloud/device/dev-abc/in/hash123/sta',
        ], $topics);
    }

    public function test_it_returns_no_topics_when_the_config_has_no_templates(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => []])]);

        $this->assertSame([], (new TuyaMqttService)->topicsFor($this->integration(), []));
    }

    private function integration(): Integration
    {
        $platform = Platform::create(['name' => 'Tuya SmartLife', 'slug' => 'tuya']);

        return Integration::create([
            'platform_id' => $platform->id,
            'user_id' => User::factory()->create()->id,
            'tuya_access_token' => 'access-token',
            'tuya_refresh_token' => 'refresh-token',
            'tuya_uid' => 'az-user-uid',
            'tuya_endpoint' => 'apigw.tuyaus.com',
            'tuya_token_expires_at' => now()->addHour(),
        ]);
    }

    public function test_it_stores_the_reported_status_of_a_device(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-1',
            'tuya_status_payload' => [],
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => [
                'devId' => 'dev-1',
                'status' => [['code' => 'lock_motor_state', 'value' => true]],
            ],
        ]);

        $device->refresh();
        $this->assertSame(
            [['code' => 'lock_motor_state', 'value' => true, 't' => null]],
            $device->tuya_status_payload,
        );
    }

    public function test_a_partial_report_keeps_the_other_reported_codes(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-3',
            'tuya_status_payload' => [
                ['code' => 'residual_electricity', 'value' => 51, 't' => 1000],
                ['code' => 'lock_motor_state', 'value' => false, 't' => 1000],
            ],
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => [
                'devId' => 'dev-3',
                'status' => [['47' => true, 'code' => 'lock_motor_state', 'value' => true, 't' => 2000]],
            ],
        ]);

        $this->assertSame([
            ['code' => 'residual_electricity', 'value' => 51, 't' => 1000],
            ['code' => 'lock_motor_state', 'value' => true, 't' => 2000],
        ], $device->refresh()->tuya_status_payload);
    }

    public function test_it_never_stores_the_ble_unlock_check(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-4',
            'tuya_status_payload' => [],
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => [
                'devId' => 'dev-4',
                'status' => [['code' => 'ble_unlock_check', 'value' => 'AAH//w==', 't' => 2000]],
            ],
        ]);

        $this->assertSame([], $device->refresh()->tuya_status_payload);
    }

    public function test_it_updates_online_state_from_biz_code(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-2',
            'tuya_online' => true,
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 20,
            'data' => ['bizCode' => 'offline', 'bizData' => ['devId' => 'dev-2']],
        ]);

        $this->assertFalse($device->refresh()->tuya_online);
    }

    public function test_it_ignores_messages_for_unknown_devices(): void
    {
        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => ['devId' => 'nao-existe', 'status' => []],
        ]);

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_it_broadcasts_the_lock_status_to_every_place_when_it_changes(): void
    {
        Event::fake([PlaceTuyaLockStatusEvent::class]);

        $device = $this->tuyaLock('dev-10', [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]]);
        $first = Place::create(['name' => 'Casa 1']);
        $second = Place::create(['name' => 'Casa 2']);
        $device->places()->attach([$first->id, $second->id]);

        $this->report('dev-10', [['code' => 'lock_motor_state', 'value' => true, 't' => 2000]]);

        Event::assertDispatchedTimes(PlaceTuyaLockStatusEvent::class, 2);
        Event::assertDispatched(
            PlaceTuyaLockStatusEvent::class,
            fn (PlaceTuyaLockStatusEvent $event): bool => $event->placeId === $first->id
                && $event->deviceId === $device->id
                && $event->status['locked'] === false,
        );
    }

    public function test_it_does_not_broadcast_when_the_lock_status_did_not_change(): void
    {
        Event::fake([PlaceTuyaLockStatusEvent::class]);

        $device = $this->tuyaLock('dev-11', [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]]);
        $device->places()->attach(Place::create(['name' => 'Casa'])->id);

        // Mesmo valor e mesmo `t`: é o mesmo relatório chegando por /sta e /pen.
        $this->report('dev-11', [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]]);
        // DP que o painel não usa.
        $this->report('dev-11', [['code' => 'doorbell_volume', 'value' => 'mute', 't' => 3000]]);

        Event::assertNotDispatched(PlaceTuyaLockStatusEvent::class);
    }

    public function test_it_does_not_broadcast_lock_status_for_a_device_that_is_not_a_lock(): void
    {
        Event::fake([PlaceTuyaLockStatusEvent::class]);

        $device = Device::create([
            'name' => 'Portão',
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'tdq',
            'external_device_id' => 'dev-12',
            'tuya_status_payload' => [],
        ]);
        $device->places()->attach(Place::create(['name' => 'Casa'])->id);

        $this->report('dev-12', [['code' => 'switch_1', 'value' => true, 't' => 2000]]);

        Event::assertNotDispatched(PlaceTuyaLockStatusEvent::class);
    }

    public function test_it_broadcasts_availability_only_when_online_changes(): void
    {
        Event::fake([PlaceDeviceStatusEvent::class]);

        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-13',
            'tuya_online' => true,
        ]);
        $place = Place::create(['name' => 'Casa']);
        $device->places()->attach($place->id);

        $service = new TuyaMqttService;
        $service->handleMessage(['protocol' => 20, 'data' => ['bizCode' => 'online', 'bizData' => ['devId' => 'dev-13']]]);
        Event::assertNotDispatched(PlaceDeviceStatusEvent::class);

        $service->handleMessage(['protocol' => 20, 'data' => ['bizCode' => 'offline', 'bizData' => ['devId' => 'dev-13']]]);
        Event::assertDispatched(
            PlaceDeviceStatusEvent::class,
            fn (PlaceDeviceStatusEvent $event): bool => $event->placeId === $place->id && $event->isAvailable === false,
        );
    }

    /** @param list<array<string, mixed>> $payload */
    private function tuyaLock(string $externalId, array $payload): Device
    {
        return Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'ms',
            'external_device_id' => $externalId,
            'tuya_status_payload' => $payload,
        ]);
    }

    /** @param list<array<string, mixed>> $status */
    private function report(string $externalId, array $status): void
    {
        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => ['devId' => $externalId, 'status' => $status],
        ]);
    }
}
