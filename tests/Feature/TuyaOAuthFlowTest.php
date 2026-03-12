<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlaceRoleEnum;
use App\Models\Place;
use App\Models\TuyaCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaOAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tuya.client_id' => 'test_client_id',
            'tuya.client_secret' => 'test_secret',
            'tuya.base_url' => 'https://openapi.tuyaus.com',
            'tuya.oauth_authorize_url' => 'https://openapi.tuyaus.com/login.action',
        ]);
    }

    public function test_redirect_sends_user_to_tuya_with_state(): void
    {
        $user = User::factory()->create();
        $place = Place::query()->create(['name' => 'My Place']);
        $place->placeUsers()->create(['user_id' => $user->id, 'role' => PlaceRoleEnum::Host]);

        $response = $this->actingAs($user)->get(route('app.places.tuya.redirect', ['place' => $place]));

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringContainsString('openapi.tuyaus.com/login.action', $target);
        $this->assertStringContainsString('client_id=test_client_id', $target);
        $this->assertStringContainsString('response_type=code', $target);
        $this->assertStringContainsString('scope=api', $target);
        $this->assertStringContainsString('state=', $target);
    }

    public function test_redirect_denied_for_user_without_place_access(): void
    {
        $user = User::factory()->create();
        $place = Place::query()->create(['name' => 'Other Place']);
        // user not in placeUsers

        $response = $this->actingAs($user)->get(route('app.places.tuya.redirect', ['place' => $place]));

        $response->assertForbidden();
    }

    public function test_callback_exchanges_code_and_stores_credentials(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'access_token' => 'at_123',
                    'refresh_token' => 'rt_456',
                    'expire_time' => 7200,
                    'uid' => 'tuya_uid',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $place = Place::query()->create(['name' => 'My Place']);
        $place->placeUsers()->create(['user_id' => $user->id, 'role' => PlaceRoleEnum::Host]);
        $state = Crypt::encryptString((string) $place->id);

        $response = $this->actingAs($user)->get(route('app.tuya.callback', [
            'code' => 'auth_code_xyz',
            'state' => $state,
        ]));

        $response->assertRedirect(route('app.places.show', ['place' => $place]));
        $response->assertSessionHas('status');

        $cred = TuyaCredential::query()->where('place_id', $place->id)->first();
        $this->assertNotNull($cred);
        $this->assertSame('tuya_uid', $cred->uid);
        $this->assertTrue($cred->expires_at->isFuture());
    }

    public function test_callback_validation_error_when_exchange_fails(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'msg' => 'Invalid code'], 200)]);

        $user = User::factory()->create();
        $place = Place::query()->create(['name' => 'My Place']);
        $place->placeUsers()->create(['user_id' => $user->id, 'role' => PlaceRoleEnum::Host]);
        $state = Crypt::encryptString((string) $place->id);

        $response = $this->actingAs($user)->get(route('app.tuya.callback', [
            'code' => 'bad_code',
            'state' => $state,
        ]));

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('tuya_credentials', 0);
    }

    public function test_callback_denied_for_user_without_place_access(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'access_token' => 'at',
                    'refresh_token' => 'rt',
                    'expire_time' => 7200,
                    'uid' => 'uid',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $place = Place::query()->create(['name' => 'Other Place']);
        // user not in placeUsers
        $state = Crypt::encryptString((string) $place->id);

        $response = $this->actingAs($user)->get(route('app.tuya.callback', [
            'code' => 'code',
            'state' => $state,
        ]));

        $response->assertForbidden();
    }
}
