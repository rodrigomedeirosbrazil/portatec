<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Services\Tuya\DTOs\TuyaAuthenticationDTO;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Client
{
    private string $accessToken;

    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        string $accessToken = '',
    ) {
        $this->accessToken = $accessToken;
    }

    public static function fromConfig(?string $accessToken = null): self
    {
        return new self(
            baseUrl: config('tuya.base_url'),
            clientId: config('tuya.client_id'),
            clientSecret: config('tuya.client_secret'),
            accessToken: $accessToken ?? '',
        );
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function isAuthenticated(): bool
    {
        return $this->accessToken !== '';
    }

    /**
     * Exchange OAuth authorization code for tokens. Uses empty access_token for signature.
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): ?TuyaAuthenticationDTO
    {
        $urlPath = '/v1.0/token?grant_type=2&code='.urlencode($code).'&redirect_uri='.urlencode($redirectUri);
        $response = $this->sendRequestWithAccessToken(
            method: Request::METHOD_GET,
            urlPath: $urlPath,
            accessToken: '',
        );

        if (! $response->successful() || ! $response->json('success')) {
            return null;
        }

        $data = $response->json();
        $result = $data['result'] ?? [];

        return new TuyaAuthenticationDTO(
            accessToken: (string) ($result['access_token'] ?? ''),
            refreshToken: (string) ($result['refresh_token'] ?? ''),
            expireTime: (string) ($result['expire_time'] ?? '0'),
            uid: (string) ($result['uid'] ?? ''),
        );
    }

    /**
     * Refresh tokens using refresh_token. Signature uses current (possibly expired) access token.
     */
    public function refreshToken(string $refreshToken, string $currentAccessToken): ?TuyaAuthenticationDTO
    {
        $urlPath = '/v1.0/token/'.urlencode($refreshToken);
        $response = $this->sendRequestWithAccessToken(
            method: Request::METHOD_GET,
            urlPath: $urlPath,
            accessToken: $currentAccessToken,
        );

        if (! $response->successful() || ! $response->json('success')) {
            return null;
        }

        $data = $response->json();
        $result = $data['result'] ?? [];

        return new TuyaAuthenticationDTO(
            accessToken: (string) ($result['access_token'] ?? ''),
            refreshToken: (string) ($result['refresh_token'] ?? ''),
            expireTime: (string) ($result['expire_time'] ?? '0'),
            uid: (string) ($result['uid'] ?? ''),
        );
    }

    public function sendRequest(string $method, string $urlPath, ?array $body = null): Response
    {
        return $this->sendRequestWithAccessToken($method, $urlPath, $this->accessToken, $body);
    }

    private function sendRequestWithAccessToken(
        string $method,
        string $urlPath,
        string $accessToken,
        ?array $body = null,
    ): Response {
        $stringRequest = $this->getStringRequest($method, [], $body, $urlPath);
        $timestamp = (string) (now()->timestamp * 1000);
        $nonce = '';

        $sign = $this->getSignString(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            accessToken: $accessToken,
            timestamp: $timestamp,
            nonce: $nonce,
            stringRequest: $stringRequest,
        );

        $headers = [
            'client_id' => $this->clientId,
            'sign' => $sign,
            't' => $timestamp,
            'sign_method' => 'HMAC-SHA256',
        ];

        if ($accessToken !== '') {
            $headers['access_token'] = $accessToken;
        }

        $http = Http::baseUrl($this->baseUrl);

        return $http
            ->withHeaders($headers)
            ->send($method, $urlPath, ['json' => $body]);
    }

    public function getSignString(
        string $clientId,
        string $clientSecret,
        string $accessToken,
        string $timestamp,
        string $nonce,
        string $stringRequest,
    ): string {
        $str = $clientId.$accessToken.$timestamp.$nonce.$stringRequest;
        $hash = hash_hmac('sha256', $str, $clientSecret, false);

        return strtoupper($hash);
    }

    public function getStringRequest(string $method, ?array $headers, ?array $body, string $urlPath): string
    {
        $jsonBody = $body === null ? '' : json_encode($body);
        $hashedBody = hash('sha256', $jsonBody);
        $headersString = $headers === null ? '' : collect($headers)
            ->sortKeys()
            ->map(fn ($value, $key) => "{$key}:{$value}")
            ->implode(PHP_EOL);

        return implode(PHP_EOL, [$method, $hashedBody, $headersString, $urlPath]);
    }
}
