<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Models\TuyaAccount;
use App\Models\TuyaDevice;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TuyaSharingService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('tuya.sharing.service_url'), '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): Response
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');

        return Http::timeout(20)->post($url, $payload);
    }

    /**
     * @return array{token: string, expire_time: int, qr_payload: string}|null
     */
    public function createQr(string $userCode): ?array
    {
        $payload = [
            'client_id' => config('tuya.sharing.client_id'),
            'schema' => config('tuya.sharing.schema'),
            'user_code' => $userCode,
        ];

        $response = $this->post('/sharing/qr', $payload);

        if (! $response->successful()) {
            $this->logApiError('createQr', $response);

            return null;
        }

        $result = $response->json('result', $response->json());
        $token = (string) ($result['token'] ?? '');
        if ($token === '') {
            $this->logApiError('createQr', $response, 'missing token');

            return null;
        }

        $expire = (int) ($result['expire_time'] ?? 300);
        $qrPayload = (string) ($result['qr_payload'] ?? '');
        if ($qrPayload === '') {
            $prefix = (string) config('tuya.sharing.qr_payload_prefix', 'tuyaSmart--qrLogin?token=');
            $qrPayload = $prefix.$token;
        }

        return [
            'token' => $token,
            'expire_time' => $expire,
            'qr_payload' => $qrPayload,
        ];
    }

    /**
     * @return array{ok: bool, uid?: string, token_info?: array, terminal_id?: string, endpoint?: string, expire_time?: int}|null
     */
    public function pollLogin(string $userCode, string $token): ?array
    {
        $payload = [
            'client_id' => config('tuya.sharing.client_id'),
            'schema' => config('tuya.sharing.schema'),
            'user_code' => $userCode,
            'token' => $token,
        ];

        $response = $this->post('/sharing/login-result', $payload);

        if (! $response->successful()) {
            $this->logApiError('pollLogin', $response);

            return null;
        }

        $result = $response->json('result', $response->json());
        $ok = (bool) ($result['ok'] ?? $response->json('ok', false));
        if (! $ok) {
            return ['ok' => false];
        }

        return [
            'ok' => true,
            'uid' => (string) ($result['uid'] ?? ''),
            'token_info' => Arr::get($result, 'token_info', $result['tokenInfo'] ?? null),
            'terminal_id' => (string) ($result['terminal_id'] ?? $result['terminalId'] ?? ''),
            'endpoint' => (string) ($result['endpoint'] ?? ''),
            'expire_time' => (int) ($result['expire_time'] ?? $result['expires_in'] ?? 7200),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, category?: string, online?: bool, status?: array}>|null
     */
    public function listDevices(TuyaAccount $account): ?array
    {
        $payload = [
            'client_id' => config('tuya.sharing.client_id'),
            'schema' => config('tuya.sharing.schema'),
            'user_code' => $account->user_code,
            'token_info' => $account->token_info,
            'terminal_id' => $account->terminal_id,
            'endpoint' => $account->endpoint,
        ];

        $response = $this->post('/sharing/devices', $payload);

        if (! $response->successful()) {
            $this->logApiError('listDevices', $response);

            return null;
        }

        $devices = $response->json('result.devices', $response->json('devices', []));
        if (! is_array($devices)) {
            $this->logApiError('listDevices', $response, 'invalid devices');

            return null;
        }

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
        $payload = [
            'client_id' => config('tuya.sharing.client_id'),
            'schema' => config('tuya.sharing.schema'),
            'user_code' => $account->user_code,
            'token_info' => $account->token_info,
            'terminal_id' => $account->terminal_id,
            'endpoint' => $account->endpoint,
            'device_id' => $deviceId,
            'commands' => $commands,
        ];

        $response = $this->post('/sharing/command', $payload);

        if (! $response->successful()) {
            $this->logApiError('sendCommand', $response);

            return false;
        }

        return (bool) $response->json('ok', $response->json('success', false));
    }

    public function disconnect(TuyaAccount $account): bool
    {
        $payload = [
            'client_id' => config('tuya.sharing.client_id'),
            'schema' => config('tuya.sharing.schema'),
            'user_code' => $account->user_code,
            'token_info' => $account->token_info,
            'terminal_id' => $account->terminal_id,
            'endpoint' => $account->endpoint,
        ];

        $response = $this->post('/sharing/unload', $payload);

        if (! $response->successful()) {
            $this->logApiError('disconnect', $response);

            return false;
        }

        return (bool) $response->json('ok', $response->json('success', false));
    }

    private function logApiError(string $method, Response $response, ?string $detail = null): void
    {
        $context = [
            'method' => $method,
            'status' => $response->status(),
            'body' => $response->body(),
        ];
        if ($detail !== null) {
            $context['detail'] = $detail;
        }
        Log::warning('Tuya Sharing API error', $context);
    }
}
