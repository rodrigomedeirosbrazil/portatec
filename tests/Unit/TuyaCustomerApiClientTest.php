<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\TuyaApiException;
use App\Models\Integration;
use App\Models\Platform;
use App\Models\User;
use App\Services\Tuya\TuyaCustomerApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaCustomerApiClientTest extends TestCase
{
    use RefreshDatabase;

    /** O repositório não usa factories (só UserFactory) — crie os models na mão. */
    private function integration(array $overrides = []): Integration
    {
        $platform = Platform::create(['name' => 'Tuya SmartLife', 'slug' => 'tuya']);
        $user = User::factory()->create();

        return Integration::create(array_merge([
            'platform_id' => $platform->id,
            'user_id' => $user->id,
            'tuya_access_token' => 'access-token',
            'tuya_refresh_token' => 'refresh-token',
            'tuya_endpoint' => 'apigw.tuyaus.com',
            'tuya_token_expires_at' => now()->addHour(),
        ], $overrides));
    }

    /** Cifra um payload do mesmo jeito que o servidor Tuya faria, para o fake. */
    private function encryptAsServer(string $plain, string $secret): string
    {
        $nonce = 'ABCDEFGHJKMN';
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-128-gcm', $secret, OPENSSL_RAW_DATA, $nonce, $tag);

        return base64_encode($nonce).base64_encode($cipher.$tag);
    }

    public function test_it_uses_the_regional_endpoint_and_signs_the_request(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => null])]);

        (new TuyaCustomerApiClient)->get($this->integration(), '/v1.0/m/life/users/homes');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://apigw.tuyaus.com/v1.0/m/life/users/homes')
                && $request->hasHeader('X-appKey', 'HA_3y9q4ak7g4ephrvke')
                && $request->hasHeader('X-token', 'access-token')
                && $request->header('X-sign')[0] !== ''
                && $request->method() === 'GET';
        });
    }

    public function test_it_posts_when_the_method_is_post_even_without_body(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => null])]);

        (new TuyaCustomerApiClient)->post($this->integration(), '/v1.0/m/life/ping');

        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_it_decodes_boolean_result(): void
    {
        $client = new TuyaCustomerApiClient;
        $secret = '0123456789abcdef';

        $decoded = $this->invokeDecode($client, $this->encryptAsServer('true', $secret), $secret);

        $this->assertTrue($decoded);
    }

    public function test_it_decodes_array_result(): void
    {
        $client = new TuyaCustomerApiClient;
        $secret = '0123456789abcdef';

        $decoded = $this->invokeDecode($client, $this->encryptAsServer('[{"id":"a"}]', $secret), $secret);

        $this->assertSame([['id' => 'a']], $decoded);
    }

    public function test_it_throws_when_tuya_reports_failure(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response([
            'success' => false,
            'code' => 1004,
            'msg' => 'sign invalid',
        ])]);

        $this->expectException(TuyaApiException::class);
        $this->expectExceptionMessageMatches('/sign invalid/');

        (new TuyaCustomerApiClient)->get($this->integration(), '/v1.0/m/life/users/homes');
    }

    public function test_it_throws_on_http_error(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response('boom', 500)]);

        $this->expectException(TuyaApiException::class);

        (new TuyaCustomerApiClient)->get($this->integration(), '/v1.0/m/life/users/homes');
    }

    private function invokeDecode(TuyaCustomerApiClient $client, string $cipher, string $secret): mixed
    {
        $method = new \ReflectionMethod($client, 'decodeResult');

        return $method->invoke($client, $cipher, $secret);
    }
}
