<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tuya\TuyaQrAuthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaQrAuthServiceTest extends TestCase
{
    public function test_it_requests_the_qr_code_with_the_haauthorize_schema(): void
    {
        Http::fake(['apigw.iotbing.com/*' => Http::response([
            'success' => true,
            'result' => ['qrcode' => 'token-abc', 'expire_time' => 300],
        ])]);

        $dto = (new TuyaQrAuthService)->generateQrCode('user-code-1');

        $this->assertNotNull($dto);
        $this->assertSame('tuyaSmart--qrLogin?token=token-abc', $dto->qrUrl);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'schema=haauthorize')
            && str_contains($request->url(), 'clientid=HA_3y9q4ak7g4ephrvke')
            && str_contains($request->url(), 'usercode=user-code-1'));
    }

    public function test_it_captures_terminal_id_and_server_time_from_the_login_result(): void
    {
        Http::fake(['apigw.iotbing.com/*' => Http::response([
            'success' => true,
            't' => 1770000000000,
            'result' => [
                'access_token' => 'access',
                'refresh_token' => 'refresh',
                'uid' => 'uid-1',
                'expire_time' => 7200,
                'endpoint' => 'https://apigw.tuyaus.com',
                'terminal_id' => 'terminal-1',
            ],
        ])]);

        $token = (new TuyaQrAuthService)->pollLogin('token-abc', 'user-code-1');

        $this->assertNotNull($token);
        $this->assertSame('terminal-1', $token->terminalId);
        $this->assertSame(1770000000000, $token->serverTime);
        $this->assertSame('https://apigw.tuyaus.com', $token->endpoint);
    }
}
