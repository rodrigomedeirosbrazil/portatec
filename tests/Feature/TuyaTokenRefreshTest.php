<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Platform;
use App\Models\User;
use App\Services\Tuya\TuyaCustomerApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function integration(array $overrides): Integration
    {
        $platform = Platform::create(['name' => 'Tuya SmartLife', 'slug' => 'tuya']);

        return Integration::create(array_merge([
            'platform_id' => $platform->id,
            'user_id' => User::factory()->create()->id,
        ], $overrides));
    }

    public function test_it_refreshes_an_expired_token_before_the_real_request(): void
    {
        $integration = $this->integration([
            'tuya_access_token' => 'old-access',
            'tuya_refresh_token' => 'old-refresh',
            'tuya_endpoint' => 'apigw.tuyaus.com',
            'tuya_token_expires_at' => now()->subMinutes(5),
        ]);

        Http::fake([
            'apigw.tuyaus.com/v1.0/m/token/old-refresh' => Http::response([
                'success' => true,
                'result' => [
                    'accessToken' => 'new-access',
                    'refreshToken' => 'new-refresh',
                    'uid' => 'uid-1',
                    'expireTime' => 7200,
                ],
            ]),
            'apigw.tuyaus.com/v1.0/m/life/users/homes' => Http::response([
                'success' => true,
                'result' => null,
            ]),
        ]);

        (new TuyaCustomerApiClient)->get($integration, '/v1.0/m/life/users/homes');

        $integration->refresh();
        $this->assertSame('new-access', $integration->tuya_access_token);
        $this->assertSame('new-refresh', $integration->tuya_refresh_token);
        $this->assertTrue($integration->tuya_token_expires_at->isFuture());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1.0/m/token/old-refresh'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/users/homes')
            && $request->hasHeader('X-token', 'new-access'));
    }

    public function test_it_does_not_refresh_a_valid_token(): void
    {
        $integration = $this->integration([
            'tuya_access_token' => 'access',
            'tuya_refresh_token' => 'refresh',
            'tuya_endpoint' => 'apigw.tuyaus.com',
            'tuya_token_expires_at' => now()->addHour(),
        ]);

        Http::fake(['apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => null])]);

        (new TuyaCustomerApiClient)->get($integration, '/v1.0/m/life/users/homes');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1.0/m/token/'));
    }
}
