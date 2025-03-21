<?php

namespace App\Services;

use App\DTOs\TuyaAuthenticationDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TuyaService
{
    private $clientId;
    private $clientSecret;

    private const API_URL = 'https://openapi.tuyaus.com';

    public function __construct()
    {
        $this->clientId = env('TUYA_CLIENT_ID');
        $this->clientSecret = env('TUYA_CLIENT_SECRET');
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
}
