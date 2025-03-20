<?php

namespace App\Services;

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

    public function getAccessToken()
    {
        $urlPath = '/v1.0/token?grant_type=1';

        $method = 'GET';
        $headers = [];
        $body = '';
        $signStr = $this->calcSignString($method, $headers, $body, $urlPath); // e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855

        $timestamp = now()->timestamp * 1000;
        $nonce = '';

        $sign = $this->calculateSign($timestamp, $nonce, $signStr); // 8D8FC41B14C165C245BC4CCC8BDAC0BECE80B8EEB1EABB1E94585B23FD882C1B

        $response = Http::withHeaders([
            'client_id' => $this->clientId,
            'sign' => $sign,
            't' => $timestamp,
            'sign_method' => 'HMAC-SHA256',
        ])->send($method, self::API_URL . $urlPath);

        dd($response->body());
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
