<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\TuyaAccount;
use App\Models\TuyaDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tuya.client_id' => 'test_client_id',
            'tuya.client_secret' => 'test_client_secret',
            'tuya.base_url' => 'https://openapi.tuyaus.com',
            'tuya.schema' => 'smartlife',
            'tuya.sharing.service_url' => 'https://tuya-sharing.test',
            'tuya.sharing.client_id' => 'sharing_client_id',
            'tuya.sharing.schema' => 'haauthorize',
        ]);
    }

    public function test_show_qr_redirects_to_devices_when_account_exists(): void
    {
        $user = User::factory()->create();
        TuyaAccount::factory()->create(['user_id' => $user->id, 'active' => true]);

        $response = $this->actingAs($user)->get(route('app.tuya.connect'));

        $response->assertRedirect(route('app.tuya.devices'));
    }

    public function test_show_qr_shows_form_when_no_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.tuya.connect'));

        $response->assertOk();
        $response->assertSee('Código do Usuário', false);
    }

    public function test_start_connect_shows_error_when_qr_fails(): void
    {
        Http::fake([config('tuya.sharing.service_url').'/sharing/qr' => Http::response([], 500)]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('app.tuya.connect.start'), [
            'user_code' => 'user-code',
        ]);

        $response->assertOk();
        $response->assertSee('Não foi possível gerar o QR code', false);
    }

    public function test_start_connect_renders_qr_when_successful(): void
    {
        Http::fake([
            config('tuya.sharing.service_url').'/sharing/qr' => Http::response([
                'result' => [
                    'token' => 'qr-token',
                    'expire_time' => 120,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('app.tuya.connect.start'), [
            'user_code' => 'user-code',
        ]);

        $response->assertOk();
        $response->assertSee('Este código expira', false);
    }

    public function test_poll_login_returns_linked_false_when_missing_user_code(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('app.tuya.poll', ['token' => 'some-token']));

        $response->assertOk();
        $response->assertJson(['linked' => false, 'error' => 'missing_user_code']);
    }

    public function test_poll_login_creates_account_and_returns_linked_true(): void
    {
        Http::fake([
            config('tuya.sharing.service_url').'/sharing/login-result' => Http::response([
                'result' => [
                    'ok' => true,
                    'uid' => 'tuya-uid-123',
                    'token_info' => [
                        'access_token' => 'at',
                        'refresh_token' => 'rt',
                    ],
                    'terminal_id' => 'term-1',
                    'endpoint' => 'https://openapi.tuyaus.com',
                    'expire_time' => 7200,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['tuya_user_code' => 'user-code'])
            ->getJson(route('app.tuya.poll', ['token' => 'some-token']));

        $response->assertOk();
        $response->assertJson(['linked' => true]);

        $this->assertDatabaseHas('tuya_accounts', [
            'user_id' => $user->id,
            'uid' => 'tuya-uid-123',
            'active' => true,
        ]);
    }

    public function test_list_devices_redirects_to_connect_when_no_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.tuya.devices'));

        $response->assertRedirect(route('app.tuya.connect'));
    }

    public function test_list_devices_shows_page_when_account_exists(): void
    {
        $user = User::factory()->create();
        $account = TuyaAccount::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'user_code' => 'user-code',
            'token_info' => ['access_token' => 'at'],
            'terminal_id' => 'term-1',
            'endpoint' => 'https://openapi.tuyaus.com',
        ]);

        Http::fake([
            config('tuya.sharing.service_url').'/sharing/devices' => Http::response([
                'result' => ['devices' => []],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get(route('app.tuya.devices'));

        $response->assertOk();
        $response->assertSee('Dispositivos Tuya');
    }

    public function test_assign_device_to_place_updates_device(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->create();
        $user->places()->attach($place->id, ['role' => 'host', 'label' => 'Anfitrião']);
        $account = TuyaAccount::factory()->create(['user_id' => $user->id]);
        $device = TuyaDevice::factory()->create(['tuya_account_id' => $account->id, 'place_id' => null]);

        $response = $this->actingAs($user)->post(route('app.tuya.devices.assign-place'), [
            'tuya_device_id' => $device->id,
            'place_id' => $place->id,
        ]);

        $response->assertRedirect(route('app.tuya.devices'));
        $device->refresh();
        $this->assertSame((int) $place->id, (int) $device->place_id);
    }

    public function test_assign_device_to_place_denies_other_users_place(): void
    {
        $user = User::factory()->create();
        $otherPlace = Place::factory()->create();
        $account = TuyaAccount::factory()->create(['user_id' => $user->id]);
        $device = TuyaDevice::factory()->create(['tuya_account_id' => $account->id]);

        $response = $this->actingAs($user)->post(route('app.tuya.devices.assign-place'), [
            'tuya_device_id' => $device->id,
            'place_id' => $otherPlace->id,
        ]);

        $response->assertForbidden();
    }

    public function test_disconnect_sets_account_inactive(): void
    {
        $user = User::factory()->create();
        $account = TuyaAccount::factory()->create(['user_id' => $user->id, 'active' => true]);

        $response = $this->actingAs($user)->delete(route('app.tuya.disconnect'));

        $response->assertRedirect(route('app.dashboard'));
        $account->refresh();
        $this->assertFalse($account->active);
    }
}
