<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaConnectWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_a_qr_code_stores_state_in_session_and_does_not_return_the_secret(): void
    {
        Http::fake([
            'apigw.iotbing.com/*' => Http::response([
                'success' => true,
                'result' => [
                    'qrcode' => 'super-secret-poll-token',
                    'expire_time' => 300,
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/devices/integrations/tuya-connect/qr', ['user_code' => 'user-code-123'])
            ->assertOk();

        // qrUrl is expected to embed the poll token — that IS the public QR
        // content the user scans with the Tuya app (see the class docblock
        // and the migration spec: "qrUrl (conteudo publico do QR)" is
        // allowed to reach the browser). The response must expose exactly
        // that shape and nothing more — no separate `qrCode` field for the
        // client to read or echo back.
        $response->assertExactJson([
            'step' => 'qr',
            'qrUrl' => 'tuyaSmart--qrLogin?token=super-secret-poll-token',
            'qrExpiresAt' => $response->json('qrExpiresAt'),
        ]);

        $this->assertSame('qr', session('tuya_connect.step'));
        $this->assertSame('super-secret-poll-token', session('tuya_connect.qr_code'));
    }

    public function test_polling_confirms_login_and_response_never_contains_tokens(): void
    {
        Http::fake([
            'apigw.iotbing.com/*' => Http::response([
                'success' => true,
                'result' => [
                    'access_token' => 'top-secret-access-token',
                    'refresh_token' => 'top-secret-refresh-token',
                    'uid' => 'tuya-uid-1',
                    'expire_time' => 7200,
                    'terminal_id' => 'terminal-xyz',
                ],
            ]),
            'apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => []]),
        ]);

        $user = User::factory()->create();

        $this->withSession([
            'tuya_connect.step' => 'qr',
            'tuya_connect.qr_code' => 'poll-token',
            'tuya_connect.user_code' => 'user-code-123',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/app/devices/integrations/tuya-connect/qr/poll')
            ->assertOk();

        $raw = $response->getContent();
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('top-secret-access-token', $raw);
        $this->assertStringNotContainsString('top-secret-refresh-token', $raw);
        $this->assertStringNotContainsString('terminal-xyz', $raw);

        $this->assertSame('confirmed', $response->json('status'));
        $this->assertSame('top-secret-access-token', session('tuya_connect.token.access_token'));
    }

    public function test_tuya_connect_page_never_leaks_tokens_in_its_raw_html(): void
    {
        $user = User::factory()->create();

        $this->withSession([
            'tuya_connect.step' => 'devices',
            'tuya_connect.token' => [
                'access_token' => 'leaked-if-shown-access',
                'refresh_token' => 'leaked-if-shown-refresh',
                'expire_time' => 7200,
                'uid' => 'uid-1',
                'terminal_id' => 'leaked-terminal-id',
            ],
            'tuya_connect.devices' => [
                ['id' => 'device-1', 'name' => 'Fechadura', 'category' => 'ms', 'categoryLabel' => 'Fechadura', 'online' => true, 'productId' => null, 'productName' => null, 'icon' => null, 'status' => [], 'selected' => true],
            ],
        ]);

        $response = $this->actingAs($user)
            ->get('/app/devices/integrations/tuya-connect')
            ->assertOk();

        $response->assertDontSee('leaked-if-shown-access', false);
        $response->assertDontSee('leaked-if-shown-refresh', false);
        $response->assertDontSee('leaked-terminal-id', false);
    }

    public function test_saving_without_a_token_in_session_bounces_back_to_the_form_step(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/devices/integrations/tuya-connect', ['device_ids' => ['device-1']])
            ->assertRedirect(route('app.devices.integrations.tuya-connect'));

        $this->assertSame('form', session('tuya_connect.step'));
        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_saving_creates_the_integration_and_selected_devices_from_session_metadata(): void
    {
        $user = User::factory()->create();

        $this->withSession([
            'tuya_connect.token' => [
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expire_time' => 7200,
                'uid' => 'tuya-uid-1',
                'endpoint' => 'https://openapi.tuyaus.com',
            ],
            'tuya_connect.user_code' => 'user-code-123',
            'tuya_connect.devices' => [
                [
                    'id' => 'device-1',
                    'name' => 'IFR 1001',
                    'category' => 'ms',
                    'categoryLabel' => 'Fechadura',
                    'online' => true,
                    'productId' => 'product-1',
                    'productName' => 'Lock Product',
                    'icon' => 'https://example.test/icon.png',
                    'status' => [['code' => 'doorcontact_state', 'value' => true]],
                    'selected' => true,
                ],
                [
                    'id' => 'device-2',
                    'name' => 'Nao selecionado',
                    'category' => 'ms',
                    'categoryLabel' => 'Fechadura',
                    'online' => true,
                    'productId' => null,
                    'productName' => null,
                    'icon' => null,
                    'status' => [],
                    'selected' => false,
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post('/app/devices/integrations/tuya-connect', ['device_ids' => ['device-1']])
            ->assertRedirect(route('app.devices.integrations.tuya-connect'));

        $integration = Integration::query()->where('tuya_uid', 'tuya-uid-1')->firstOrFail();
        $this->assertSame($user->id, $integration->user_id);

        $this->assertDatabaseHas('devices', [
            'external_device_id' => 'device-1',
            'integration_id' => $integration->id,
            'tuya_category' => 'ms',
            'tuya_product_id' => 'product-1',
            'tuya_product_name' => 'Lock Product',
            'tuya_online' => true,
        ]);

        // Device metadata came from the session, not from the (minimal) request payload.
        $this->assertDatabaseMissing('devices', ['external_device_id' => 'device-2']);
    }

    public function test_saving_ignores_a_device_id_not_present_in_the_session_snapshot(): void
    {
        $user = User::factory()->create();

        $this->withSession([
            'tuya_connect.token' => [
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expire_time' => 7200,
                'uid' => 'tuya-uid-2',
            ],
            'tuya_connect.user_code' => 'user-code-123',
            'tuya_connect.devices' => [],
        ]);

        // A malicious client tries to smuggle in a forged device id with no
        // corresponding metadata in the session snapshot.
        $this->actingAs($user)
            ->post('/app/devices/integrations/tuya-connect', ['device_ids' => ['forged-device']])
            ->assertRedirect(route('app.devices.integrations.tuya-connect'));

        $this->assertDatabaseMissing('devices', ['external_device_id' => 'forged-device']);
    }
}
