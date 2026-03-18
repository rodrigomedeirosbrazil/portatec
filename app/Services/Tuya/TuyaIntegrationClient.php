<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Models\Integration;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TuyaIntegrationClient
{
    public function __construct(
        private readonly Integration $integration,
    ) {}

    public function integration(): Integration
    {
        return $this->integration;
    }

    public function sendRequest(string $method, string $urlPath, ?array $body = null): Response
    {
        $stringRequest = $this->getStringRequest(
            method: $method,
            headers: [],
            body: $body,
            urlPath: $urlPath,
        );

        $timestamp = now()->timestamp * 1000;
        $sign = $this->getSignString(
            clientId: (string) config('tuya.client_id'),
            clientSecret: (string) config('tuya.client_secret'),
            accessToken: (string) ($this->integration->tuya_access_token ?? ''),
            timestamp: (string) $timestamp,
            nonce: '',
            stringRequest: $stringRequest,
        );

        $headers = [
            'client_id' => (string) config('tuya.client_id'),
            'sign' => $sign,
            't' => $timestamp,
            'sign_method' => 'HMAC-SHA256',
        ];

        if ($this->integration->tuya_access_token) {
            $headers['access_token'] = $this->integration->tuya_access_token;
        }

        return Http::baseUrl($this->baseUrl())
            ->replaceHeaders($headers)
            ->send($method, $urlPath, [
                'json' => $body,
            ]);
    }

    public function refreshToken(): bool
    {
        $refreshToken = (string) $this->integration->tuya_refresh_token;
        if ($refreshToken === '') {
            return false;
        }

        $urlPath = "/v1.0/token/{$refreshToken}";
        $response = $this->sendRequest(Request::METHOD_GET, $urlPath);

        if (! ($response->successful() && $response->json('success'))) {
            return false;
        }

        $result = (array) $response->json('result', []);
        $accessToken = data_get($result, 'access_token');
        $newRefreshToken = data_get($result, 'refresh_token');
        $expireTime = (int) data_get($result, 'expire_time', 0);
        if (! is_string($accessToken) || ! is_string($newRefreshToken) || $accessToken === '' || $newRefreshToken === '') {
            return false;
        }

        $this->integration->forceFill([
            'tuya_access_token' => $accessToken,
            'tuya_refresh_token' => $newRefreshToken,
            'tuya_token_expires_at' => now()->addSeconds($expireTime > 0 ? $expireTime : 7200),
        ])->save();

        return true;
    }

    public function baseUrl(): string
    {
        $baseUrl = (string) ($this->integration->tuya_endpoint ?: config('tuya.base_url'));
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'https://'.$baseUrl;
        }

        return rtrim($baseUrl, '/');
    }

    private function getSignString(string $clientId, string $clientSecret, string $accessToken, string $timestamp, string $nonce, string $stringRequest): string
    {
        $str = implode('', [
            $clientId,
            $accessToken,
            $timestamp,
            $nonce,
            $stringRequest,
        ]);

        return strtoupper(hash_hmac('sha256', $str, $clientSecret, false));
    }

    private function getStringRequest(string $method, ?array $headers, ?array $body, string $urlPath): string
    {
        $jsonBody = $body === null ? '' : json_encode($body);
        $hashedBody = hash('sha256', $jsonBody);
        $headersString = $headers === null ? '' : collect($headers)
            ->ksort()
            ->map(fn ($value, $key) => "$key:$value")
            ->implode(PHP_EOL);

        return implode(PHP_EOL, [
            $method,
            $hashedBody,
            $headersString,
            $urlPath,
        ]);
    }
}
