<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tuya\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['tuya.client_id' => 'test_client_id']);
        config(['tuya.client_secret' => 'test_client_secret']);
        config(['tuya.base_url' => 'https://openapi.tuyaus.com']);
    }

    public function test_string_request(): void
    {
        $uid = '12345678901234567890';
        $expectedStringRequest = 'GET'.PHP_EOL.'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'.PHP_EOL.''.PHP_EOL."/v1.0/users/{$uid}/devices";

        Http::fake();
        $client = Client::fromConfig();

        $stringRequest = $client->getStringRequest(
            method: Request::METHOD_GET,
            headers: [],
            body: null,
            urlPath: "/v1.0/users/{$uid}/devices",
        );

        $this->assertEquals(
            $expectedStringRequest,
            $stringRequest
        );
    }

    public function test_sign_string(): void
    {
        $clientId = '12345678901234567890';
        $clientSecret = '12345678901234567890123456789012';
        $uid = '12345678901234567890';
        $accessToken = '12345678901234567890';

        $expectedSign = 'EB4B380CA7851EF26CECA0A277A2B57442233653DB0A1CCB25864C2C4547896D';

        $stringRequest = 'GET'
            .PHP_EOL
            .'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
            .PHP_EOL
            .''
            .PHP_EOL
            ."/v1.0/users/{$uid}/devices";

        Http::fake();
        $client = Client::fromConfig();

        $timestamp = '1743034828181';
        $nonce = '';

        $sign = $client->getSignString(
            clientId: $clientId,
            clientSecret: $clientSecret,
            accessToken: $accessToken,
            timestamp: $timestamp,
            nonce: $nonce,
            stringRequest: $stringRequest,
        );

        $this->assertEquals(
            $expectedSign,
            $sign
        );
    }

    public function test_exchange_code_for_token_returns_dto_on_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'access_token' => 'new_at',
                    'refresh_token' => 'new_rt',
                    'expire_time' => 7200,
                    'uid' => 'tuya_uid_1',
                ],
            ], 200),
        ]);

        $client = Client::fromConfig();
        $dto = $client->exchangeCodeForToken('auth_code_123', 'https://app.example/callback');

        $this->assertNotNull($dto);
        $this->assertSame('new_at', $dto->accessToken);
        $this->assertSame('new_rt', $dto->refreshToken);
        $this->assertSame('7200', $dto->expireTime);
        $this->assertSame('tuya_uid_1', $dto->uid);
    }

    public function test_exchange_code_for_token_returns_null_on_failure(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'msg' => 'Invalid code'], 200)]);

        $client = Client::fromConfig();
        $this->assertNull($client->exchangeCodeForToken('bad_code', 'https://app.example/callback'));
    }

    public function test_refresh_token_returns_dto_on_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'access_token' => 'refreshed_at',
                    'refresh_token' => 'refreshed_rt',
                    'expire_time' => 7200,
                    'uid' => 'tuya_uid_1',
                ],
            ], 200),
        ]);

        $client = Client::fromConfig('old_access_token');
        $dto = $client->refreshToken('old_refresh_token', 'old_access_token');

        $this->assertNotNull($dto);
        $this->assertSame('refreshed_at', $dto->accessToken);
        $this->assertSame('refreshed_rt', $dto->refreshToken);
    }

    public function test_refresh_token_returns_null_on_failure(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 200)]);

        $client = Client::fromConfig();
        $this->assertNull($client->refreshToken('expired_rt', 'old_at'));
    }
}
