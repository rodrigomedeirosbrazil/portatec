<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Models\TuyaAccount;
use App\Models\TuyaDevice;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TuyaService
{
    private function buildSignature(string $method, string $path, string $body, ?string $accessToken): array
    {
        $clientId = config('tuya.client_id');
        $secret = config('tuya.client_secret');
        $t = (string) (int) (microtime(true) * 1000);
        $nonce = '';

        $bodyHash = hash('sha256', $body);
        $stringToSign = $method."\n".$bodyHash."\n\n".$path;
        $signStr = $clientId.($accessToken ?? '').$t.$nonce.$stringToSign;
        $sign = strtoupper(hash_hmac('sha256', $signStr, $secret));

        return [
            'client_id' => $clientId,
            't' => $t,
            'sign_method' => 'HMAC-SHA256',
            'sign' => $sign,
            'nonce' => $nonce,
            'Content-Type' => 'application/json',
            ...($accessToken !== null ? ['access_token' => $accessToken] : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function request(string $method, string $baseUrl, string $path, array $body = [], ?string $accessToken = null): Response
    {
        $path = '/'.ltrim($path, '/');
        $url = rtrim($baseUrl, '/').$path;
        $pathForSign = (string) (parse_url($path, PHP_URL_PATH) ?? strstr($path, '?', true) ?: $path);
        $jsonBody = $method === 'GET' ? '' : json_encode($body);
        $headers = $this->buildSignature($method, $pathForSign, $jsonBody, $accessToken);

        $request = Http::withHeaders($headers)->timeout(15);

        if ($method === 'GET') {
            return $request->get($url);
        }

        return $request->withBody($jsonBody, 'application/json')->post($url);
    }

    /**
     * @return array{qrcode: string, expire_time: int, token: string}|null
     */
    public function getQRToken(): ?array
    {
        $baseUrl = config('tuya.base_url');
        $path = '/v1.0/iot-01/associated-users/actions/authorized-login:token';
        $body = [
            'timeZoneId' => 'America/Sao_Paulo',
            'lang' => 'pt',
            'country' => '55',
            'schema' => config('tuya.schema', 'smartlife'),
        ];

        $response = $this->request('POST', $baseUrl, $path, $body, null);

        if (! $response->successful() || ! $response->json('success')) {
            $this->logApiError('getQRToken', $response);

            return null;
        }

        $result = $response->json('result');

        return $result ? [
            'qrcode' => (string) ($result['qrcode'] ?? ''),
            'expire_time' => (int) ($result['expire_time'] ?? 300),
            'token' => (string) ($result['token'] ?? ''),
        ] : null;
    }

    /**
     * @return array{is_login: bool, uid?: string, access_token?: string, refresh_token?: string, expire_time?: int, platform_url?: string}|null
     */
    public function pollLoginStatus(string $token): ?array
    {
        $baseUrl = config('tuya.base_url');
        $path = '/v1.0/iot-01/associated-users/actions/authorized-login:token/'.$token;

        $response = $this->request('GET', $baseUrl, $path, [], null);

        if (! $response->successful() || ! $response->json('success')) {
            $this->logApiError('pollLoginStatus', $response);

            return null;
        }

        $result = $response->json('result', []);
        $isLogin = (bool) ($result['is_login'] ?? false);

        $out = ['is_login' => $isLogin];
        if ($isLogin) {
            $out['uid'] = (string) ($result['uid'] ?? '');
            $out['access_token'] = (string) ($result['access_token'] ?? '');
            $out['refresh_token'] = (string) ($result['refresh_token'] ?? '');
            $out['expire_time'] = (int) ($result['expire_time'] ?? 7200);
            $out['platform_url'] = (string) ($result['platform_url'] ?? config('tuya.base_url'));
        }

        return $out;
    }

    public function refreshToken(TuyaAccount $account): bool
    {
        $baseUrl = config('tuya.base_url');
        $path = '/v1.0/token/'.$account->refresh_token;

        $response = $this->request('GET', $baseUrl, $path, [], null);

        if (! $response->successful() || ! $response->json('success')) {
            $this->logApiError('refreshToken', $response);

            return false;
        }

        $result = $response->json('result', []);
        $account->access_token = $result['access_token'] ?? '';
        $account->refresh_token = $result['refresh_token'] ?? $account->refresh_token;
        $expireTime = (int) ($result['expire_time'] ?? 7200);
        $account->expires_at = now()->addSeconds($expireTime);
        $account->save();

        return true;
    }

    /**
     * @return array<int, array{id: string, name: string, category?: string, online?: bool, status?: array}>|null
     */
    public function listDevices(TuyaAccount $account): ?array
    {
        if ($account->needsRefresh() && ! $this->refreshToken($account)) {
            return null;
        }

        $baseUrl = rtrim($account->platform_url, '/');
        $path = '/v1.2/iot-03/devices';
        $query = '?source_type=homes&source_id='.$account->uid;

        $response = $this->request(
            'GET',
            $baseUrl,
            $path.$query,
            [],
            $account->access_token
        );

        if ($response->status() === 401 || (int) $response->json('code') === 1011) {
            if ($this->refreshToken($account)) {
                return $this->listDevices($account);
            }

            return null;
        }

        if (! $response->successful() || ! $response->json('success')) {
            $this->logApiError('listDevices', $response);

            return null;
        }

        $devices = $response->json('result.devices', []);
        foreach ($devices as $device) {
            TuyaDevice::query()->updateOrCreate(
                [
                    'tuya_account_id' => $account->id,
                    'device_id' => $device['id'] ?? '',
                ],
                [
                    'name' => $device['name'] ?? 'Unknown',
                    'category' => $device['category'] ?? null,
                    'online' => (bool) ($device['online'] ?? false),
                    'status' => $device['status'] ?? null,
                ]
            );
        }

        return $devices;
    }

    /**
     * @param  array<int, array{code: string, value: mixed}>  $commands
     */
    public function sendCommand(TuyaAccount $account, string $deviceId, array $commands): bool
    {
        if ($account->needsRefresh() && ! $this->refreshToken($account)) {
            return false;
        }

        $baseUrl = rtrim($account->platform_url, '/');
        $path = '/v1.0/devices/'.$deviceId.'/commands';
        $body = ['commands' => $commands];

        $response = $this->request('POST', $baseUrl, $path, $body, $account->access_token);

        if ($response->status() === 401 || (int) $response->json('code') === 1011) {
            if ($this->refreshToken($account)) {
                return $this->sendCommand($account, $deviceId, $commands);
            }

            return false;
        }

        if (! $response->successful() || ! $response->json('success')) {
            $this->logApiError('sendCommand', $response);

            return false;
        }

        return true;
    }

    private function logApiError(string $method, Response $response): void
    {
        Log::warning('Tuya API error', [
            'method' => $method,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }
}
