<?php

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

        // Set Tuya configuration for testing
        config(['tuya.client_id' => 'test_client_id']);
        config(['tuya.client_secret' => 'test_client_secret']);
        config(['tuya.base_url' => 'https://openapi.tuyaus.com']);
    }

    public function test_string_request(): void
    {
        $uid = '12345678901234567890';
        $expectedStringRequest = 'GET'.PHP_EOL.'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'.PHP_EOL.''.PHP_EOL."/v1.0/users/{$uid}/devices";

        Http::fake();
        $client = new Client;

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
        $client = new Client;

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
}
