<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Models\Device;
use App\Models\Integration;
use App\Services\Tuya\DTOs\TuyaDeviceDTO;
use App\Services\Tuya\DTOs\TuyaTicketDTO;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TuyaIntegrationService
{
    public function listDevices(Integration $integration): Collection
    {
        if ($integration->tuya_token_expires_at?->lte(now()->addMinute())) {
            (new TuyaIntegrationClient($integration))->refreshToken();
            $integration->refresh();
        }

        $response = $this->customerRequest(
            integration: $integration,
            method: 'GET',
            path: '/v1.0/m/life/users/homes',
        );

        $homes = $response['list'] ?? $response['homes'] ?? $response ?? [];
        if (! is_array($homes)) {
            return collect();
        }

        $devices = collect();
        foreach ($homes as $home) {
            $homeId = $home['ownerId']
                ?? $home['homeId']
                ?? $home['home_id']
                ?? $home['id']
                ?? null;

            if ($homeId === null || $homeId === '') {
                continue;
            }

            $devicesResponse = $this->customerRequest(
                integration: $integration,
                method: 'GET',
                path: '/v1.0/m/life/ha/home/devices',
                params: ['homeId' => (string) $homeId],
            );

            $homeDevices = $devicesResponse['list'] ?? $devicesResponse['devices'] ?? $devicesResponse ?? [];
            if (! is_array($homeDevices)) {
                continue;
            }

            $devices = $devices->merge($homeDevices);
        }

        return $devices
            ->map(fn (array $device): TuyaDeviceDTO => new TuyaDeviceDTO(
                id: (string) ($device['id'] ?? $device['deviceId'] ?? ''),
                name: (string) ($device['name'] ?? 'Dispositivo sem nome'),
                category: (string) ($device['category'] ?? ''),
                online: (bool) ($device['online'] ?? false),
                productId: $device['product_id'] ?? $device['productId'] ?? null,
                productName: $device['product_name'] ?? $device['productName'] ?? null,
                icon: $device['icon'] ?? null,
                status: is_array($device['status'] ?? null) ? $device['status'] : [],
            ))
            ->filter(fn (TuyaDeviceDTO $device): bool => $device->id !== '')
            ->values();
    }

    public function refreshDeviceSnapshot(Device $device): ?TuyaDeviceDTO
    {
        $integration = $device->integration;
        if (! $integration instanceof Integration) {
            return null;
        }

        $snapshot = $this->listDevices($integration)
            ->first(fn (TuyaDeviceDTO $candidate): bool => $candidate->id === $device->external_device_id);

        if (! $snapshot instanceof TuyaDeviceDTO) {
            return null;
        }

        $device->forceFill([
            'name' => $snapshot->name ?: $device->name,
            'tuya_category' => $snapshot->category,
            'tuya_product_id' => $snapshot->productId,
            'tuya_product_name' => $snapshot->productName,
            'tuya_icon' => $snapshot->icon,
            'tuya_online' => $snapshot->online,
            'tuya_status_payload' => $snapshot->status,
            'last_sync' => now(),
        ])->save();

        return $snapshot;
    }

    public function createTemporaryPassword(Device $device, string $name, string $pin, ?int $effectiveTime = null, ?int $invalidTime = null): ?string
    {
        $integration = $this->resolveIntegration($device);
        $client = new TuyaIntegrationClient($integration);
        $ticket = $this->getPasswordTicket($client, (string) $device->external_device_id);
        if (! $ticket instanceof TuyaTicketDTO) {
            throw new RuntimeException('Nao foi possivel obter ticket de senha da Tuya.');
        }

        $encryptedPassword = $this->encryptPasswordWithTicket((string) config('tuya.client_secret'), $pin, $ticket);
        if ($encryptedPassword === null) {
            throw new RuntimeException('Nao foi possivel criptografar o PIN para a Tuya.');
        }

        $response = $client->sendRequest(
            method: Request::METHOD_POST,
            urlPath: "/v1.0/devices/{$device->external_device_id}/door-lock/temp-password",
            body: [
                'device_id' => $device->external_device_id,
                'name' => $name,
                'password' => $encryptedPassword,
                'effective_time' => $effectiveTime ?? now()->timestamp,
                'invalid_time' => $invalidTime ?? now()->addDay()->timestamp,
                'password_type' => 'ticket',
                'ticket_id' => $ticket->ticketId,
                'type' => 0,
            ],
        );

        if ($response->status() === 401 && $client->refreshToken()) {
            return $this->createTemporaryPassword($device, $name, $pin, $effectiveTime, $invalidTime);
        }

        if ($response->successful() && (bool) $response->json('success', false)) {
            return (string) data_get($response->json(), 'result.id');
        }

        Log::error('Failed to create Tuya temporary password', [
            'device_id' => $device->id,
            'external_device_id' => $device->external_device_id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new RuntimeException('Tuya recusou a criacao do PIN temporario.');
    }

    public function deleteTemporaryPassword(Device $device, string $passwordId): bool
    {
        $client = new TuyaIntegrationClient($this->resolveIntegration($device));
        $response = $client->sendRequest(
            method: Request::METHOD_DELETE,
            urlPath: "/v1.0/devices/{$device->external_device_id}/door-lock/temp-passwords/{$passwordId}",
        );

        if ($response->status() === 401 && $client->refreshToken()) {
            return $this->deleteTemporaryPassword($device, $passwordId);
        }

        if ($response->successful() && (bool) $response->json('success', false)) {
            return true;
        }

        Log::warning('Failed to delete Tuya temporary password', [
            'device_id' => $device->id,
            'external_device_id' => $device->external_device_id,
            'password_id' => $passwordId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    private function resolveIntegration(Device $device): Integration
    {
        $integration = $device->integration;
        if (! $integration instanceof Integration) {
            throw new RuntimeException('Dispositivo Tuya sem integracao associada.');
        }

        return $integration;
    }

    private function getPasswordTicket(TuyaIntegrationClient $client, string $deviceId): ?TuyaTicketDTO
    {
        $response = $client->sendRequest(
            method: Request::METHOD_POST,
            urlPath: "/v1.0/devices/{$deviceId}/door-lock/password-ticket",
        );

        if ($response->status() === 401 && $client->refreshToken()) {
            return $this->getPasswordTicket($client, $deviceId);
        }

        if ($response->successful() && (bool) $response->json('success', false)) {
            return new TuyaTicketDTO(
                ticketId: (string) data_get($response->json(), 'result.ticket_id'),
                ticketKey: (string) data_get($response->json(), 'result.ticket_key'),
                expireTime: (string) data_get($response->json(), 'result.expire_time'),
            );
        }

        Log::error('Failed to get Tuya password ticket', [
            'device_id' => $deviceId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    private function encryptPasswordWithTicket(string $clientSecret, string $password, TuyaTicketDTO $ticket): ?string
    {
        $decryptedKey = $this->decryptTicketKey($clientSecret, $ticket);
        if (! is_string($decryptedKey) || $decryptedKey === '') {
            return null;
        }

        $binaryPassword = openssl_encrypt($password, 'aes-128-ecb', hex2bin(bin2hex($decryptedKey)), OPENSSL_RAW_DATA);

        return $binaryPassword === false ? null : bin2hex($binaryPassword);
    }

    private function decryptTicketKey(string $clientSecret, TuyaTicketDTO $ticket): string|false
    {
        return openssl_decrypt(
            hex2bin($ticket->ticketKey),
            'aes-256-ecb',
            mb_convert_encoding($clientSecret, 'UTF-8', 'ISO-8859-1'),
            OPENSSL_RAW_DATA,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function customerRequest(Integration $integration, string $method, string $path, ?array $params = null, ?array $body = null): array
    {
        $qrService = new TuyaQrAuthService;
        $result = $qrService->customerRequest(
            $method,
            $path,
            (string) $integration->tuya_access_token,
            (string) $integration->tuya_refresh_token,
            $params,
            $body,
            $integration->tuya_endpoint,
        );

        return is_array($result) ? $result : [];
    }
}
