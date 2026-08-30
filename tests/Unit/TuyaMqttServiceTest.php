<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Models\Integration;
use App\Models\Platform;
use App\Models\User;
use App\Services\Tuya\TuyaMqttService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            [['code' => 'lock_motor_state', 'value' => true]],
            $device->tuya_status_payload,
        );
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
}
