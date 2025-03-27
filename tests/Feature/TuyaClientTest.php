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
        $expectedStringRequest = 'GET' . PHP_EOL . 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855' . PHP_EOL . '' . PHP_EOL . "/v1.0/users/{$uid}/devices";

        Http::fake();
        $client = new Client();

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

    public function testSignString(): void
    {
        $clientId = 'twfspdm9p5mkvtxe9y8n';
        $clientSecret = '12f3598e08484b518eaf495ae249ef9f';
        $uid = 'az1742250055143BSJGs';
        $accessToken = '7c59621407dce5eeada4fd5f2e0ac548';

        $expectedSign = '9D5D3F8C0F97002A1DC9A1EBB40FF0E5D17399A2ADEDA2AB414EC1CF94A30588';

        $stringRequest = 'GET'
            . PHP_EOL
            . 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
            . PHP_EOL
            . ''
            . PHP_EOL
            . "/v1.0/users/{$uid}/devices";

        Http::fake();
        $client = new Client();

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
