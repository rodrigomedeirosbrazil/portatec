<?php

namespace App\Services\Tuya;

use App\Services\Tuya\DTOs\TuyaAuthenticationDTO;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Client
{
    private $http;

    private string $clientId;

    private string $clientSecret;

    private ?TuyaAuthenticationDTO $authenticationDTO;

    public function __construct()
    {
        $this->http = Http::baseUrl(config('tuya.base_url'));
        $this->clientId = config('tuya.client_id');
        $this->clientSecret = config('tuya.client_secret');
        $this->authenticationDTO = null;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function authenticate(): bool
    {
        $response = $this->sendRequest(
            method: Request::METHOD_GET,
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
            method: Request::METHOD_GET,
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
        $stringRequest = $this->getStringRequest(
            method: $method,
            headers: [],
            body: $body,
            urlPath: $urlPath,
        );

        $timestamp = now()->timestamp * 1000;
        $nonce = '';

        $sign = $this->getSignString(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            accessToken: $this->authenticationDTO?->accessToken ?? '',
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

        if ($this->authenticationDTO?->accessToken) {
            $headers['access_token'] = $this->authenticationDTO->accessToken;
        }
        $response = $this->http
            ->replaceHeaders($headers)
            ->send($method, $urlPath, [
                'json' => $body,
            ]);

        return $response;
    }

    public function getSignString(string $clientId, string $clientSecret, string $accessToken, string $timestamp, string $nonce, string $stringRequest)
    {
        $str = implode('', [
            $clientId,
            $accessToken,
            $timestamp,
            $nonce,
            $stringRequest,
        ]);

        $hash = hash_hmac('sha256', $str, $clientSecret, false);

        return strtoupper($hash);
    }

    public function getStringRequest(string $method, ?array $headers, ?array $body, string $urlPath)
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
