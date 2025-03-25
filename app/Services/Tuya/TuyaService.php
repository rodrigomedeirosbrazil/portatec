<?php

namespace App\Services\Tuya;

use App\DTOs\TuyaAuthenticationDTO;
use Illuminate\Support\Facades\Http;

class TuyaService
{
    private $clientId;
    private $clientSecret;
    private $uid;

    private const API_URL = 'https://openapi.tuyaus.com';

    public function __construct()
    {
        $this->clientId = env('TUYA_CLIENT_ID');
        $this->clientSecret = env('TUYA_CLIENT_SECRET');
        $this->uid = env('TUYA_UID');
    }

    public function getAccessToken(): ?TuyaAuthenticationDTO
    {
        $urlPath = '/v1.0/token?grant_type=1';

        $method = 'GET';
        $headers = [];
        $body = '';
        $signStr = $this->calcSignString($method, $headers, $body, $urlPath);

        $timestamp = now()->timestamp * 1000;
        $nonce = '';

        $sign = $this->calculateSign($timestamp, $nonce, $signStr);

        $response = Http::withHeaders([
            'client_id' => $this->clientId,
            'sign' => $sign,
            't' => $timestamp,
            'sign_method' => 'HMAC-SHA256',
        ])->send($method, self::API_URL . $urlPath);

        if ($response->successful() && $response->json('success')) {
            $data = json_decode($response->body(), true);
            return new TuyaAuthenticationDTO(
                accessToken: data_get($data, 'result.access_token'),
                refreshToken: data_get($data, 'result.refresh_token'),
                expireTime: data_get($data, 'result.expire_time'),
                uid: data_get($data, 'result.uid'),
            );
        }

        return null;
    }

    private function calculateSign(string $timestamp, string $nonce, string $signStr)
    {
        $str = $this->clientId . $timestamp . $nonce . $signStr;
        $hash = hash_hmac('sha256', $str, $this->clientSecret, false);
        return strtoupper($hash);
    }

    private function calcSignString(string $method, array $headers, string $body, string $urlPath)
    {
        $sha256 = hash('sha256', $body);
        $headersString = collect($headers)
            ->map(function ($value, $key) {
                return "$key:$value";
            })
            ->sort()
            ->implode("\n");

        return join("\n", [$method, $sha256, $headersString, $urlPath]);
    }

    private function calculateSignWithAccessToken(string $timestamp, string $nonce, string $signStr, string $accessToken)
    {
        $str = $this->clientId . $accessToken . $timestamp . $nonce . $signStr;
        $hash = hash_hmac('sha256', $str, $this->clientSecret, false);
        return strtoupper($hash);
    }

    public function getDevices()
    {
        // $authenticationDTO = $this->getAccessToken();

        // if (! $authenticationDTO) {
        //     throw new \Exception('Failed to get access token');
        // }

        $authenticationDTO = new TuyaAuthenticationDTO(
            accessToken: 'daa100ca50f303d763889a4eae2e033a',
            refreshToken: 'e8324d442ed677aa2afc7d376bd40ce5',
            expireTime: 7200,
            uid: 'bay1647829239658KekG',
        );

        $urlPath = "/v1.0/users/{$this->uid}/devices";

        $method = 'GET';
        $headers = [];
        $body = '';
        $signStr = $this->calcSignString($method, $headers, $body, $urlPath);

        $timestamp = now()->timestamp * 1000;
        $nonce = '';

        $sign = $this->calculateSignWithAccessToken($timestamp, $nonce, $signStr, $authenticationDTO->accessToken);

        $response = Http::withHeaders([
            'client_id' => $this->clientId,
            'access_token' => $authenticationDTO->accessToken,
            'sign' => $sign,
            't' => $timestamp,
            'sign_method' => 'HMAC-SHA256',
        ])->send($method, self::API_URL . $urlPath);

        if ($response->successful()) {
            $data = json_decode($response->body(), true);
            return $data;
        }

        dump($response->status(), $response->body());
        return null;
    }
}
