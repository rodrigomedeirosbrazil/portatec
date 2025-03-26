<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Tuya\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TuyaClientTest extends TestCase
{
    public function testStringRequest(): void
    {
        $uid = 'az1742250055143BSJGs';
        Http::fake();
        $client = new Client();

        $stringRequest = $client->getStringRequest(
            method: Request::METHOD_GET,
            headers: [],
            body: [],
            urlPath: "/v1.0/users/{$uid}/devices",
        );

        $this->assertEquals(
            'GET' . PHP_EOL . 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855' . PHP_EOL . '' . PHP_EOL . "/v1.0/users/{$uid}/devices",
            $stringRequest
        );
    }

    public function testSignString(): void
    {
        $clientId = 'twfspdm9p5mkvtxe9y8n';
        $clientSecret = '12f3598e08484b518eaf495ae249ef9f';
        $uid = 'az1742250055143BSJGs';
        $accessToken = '3d6180a39a635aec3c3c0728e17aae72';

        $expectedSign = 'A3A3BAB4EF40A043435781E5DB82D7C573CCBA3BDB2570CEC0C98F2339A091C2';

        $stringRequest = 'GET'
            . PHP_EOL
            . 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
            . PHP_EOL
            . ''
            . PHP_EOL
            . "/v1.0/users/{$uid}/devices";

        Http::fake();
        $client = new Client();

        $timestamp = '1742953912555';
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
