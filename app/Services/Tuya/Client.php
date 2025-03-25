<?php

namespace App\Services\Tuya;

use App\Services\Tuya\DTOs\TuyaAuthenticationDTO;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Client
{
    private $http;
    private string $clientId;
    private string $clientSecret;
    private ?TuyaAuthenticationDTO $authenticationDTO;

    public function __construct() {
        $this->http = Http::baseUrl('https://openapi.tuyaus.com');
        $this->clientId = env('TUYA_CLIENT_ID');
        $this->clientSecret = env('TUYA_CLIENT_SECRET');
        $this->authenticationDTO = null;
    }

    public function authenticate(): bool
    {
        $response = $this->sendRequest(
            method: 'GET',
            urlPath: '/v1.0/token?grant_type=1',
        );

        if ($response->successful() && $response->json('success')) {
            $data = json_decode($response->body(), true);
            $this->authenticationDTO = new TuyaAuthenticationDTO(
                accessToken: data_get($data, 'result.access_token'),
                refreshToken: data_get($data, 'result.refresh_token'),
                expireTime: data_get($data, 'result.expire_time'),
                uid: data_get($data, 'result.uid'),
            );

            return true;
        }

        return false;
    }

    public function refreshToken(): bool
    {
        $urlPath = "/v1.0/token/{$this->authenticationDTO->refreshToken}";
        $response = $this->sendRequest(
            method: 'GET',
            urlPath: $urlPath,
        );

        if ($response->successful() && $response->json('success')) {
            $data = json_decode($response->body(), true);
            $this->authenticationDTO = new TuyaAuthenticationDTO(
                accessToken: data_get($data, 'result.access_token'),
                refreshToken: data_get($data, 'result.refresh_token'),
                expireTime: data_get($data, 'result.expire_time'),
                uid: data_get($data, 'result.uid'),
            );

            return true;
        }

        return false;
    }

    public function sendRequest(string $method, string $urlPath, ?array $body = null): Response
    {
        $headers = [];

        $requestString = $this->getRequestString(
            method: $method,
            headers: $headers,
            body: $body,
            urlPath: $urlPath,
        );

        $timestamp = now()->timestamp * 1000;
        $nonce = '';

        $sign = $this->getSignString(
            timestamp: $timestamp,
            nonce: $nonce,
            requestString: $requestString,
        );

        $response = $this->http->withHeaders([
            'client_id' => $this->clientId,
            'sign' => $sign,
            't' => $timestamp,
            'sign_method' => 'HMAC-SHA256',
        ])->send($method, $urlPath);

        return $response;
    }

    public function getSignString(string $timestamp, string $nonce, string $requestString)
    {
        $str = implode('', [
            $this->clientId,
            $this->authenticationDTO?->accessToken ?? '',
            $timestamp,
            $nonce,
            $requestString,
        ]);

        $hash = hash_hmac('sha256', $str, $this->clientSecret, false);
        return strtoupper($hash);
    }

    public function getRequestString(string $method, ?array $headers, ?array $body, string $urlPath)
    {
        $jsonBody = $body == null ? '' : json_encode($body);

        $hashedBody = hash('sha256', $jsonBody);

        $headersString = $headers == null ? '' : collect($headers)
            ->ksort()
            ->map(function ($value, $key) {
                return "$key:$value";
            })
            ->implode(PHP_EOL);

        return implode(PHP_EOL, [
            $method,
            $hashedBody,
            $headersString,
            $urlPath,
        ]);
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticationDTO != null;
    }
}
