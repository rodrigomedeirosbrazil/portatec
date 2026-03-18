<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Models\Device;
use App\Models\Integration;
use App\Services\Tuya\DTOs\TuyaDeviceDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TuyaIntegrationService
{
    public function listDevices(Integration $integration): Collection
    {
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

    /**
     * Teste de envio de comando simples (travar motor). Usar via tinker para diagnosticar sign invalid.
     * Ex.: (new TuyaIntegrationService)->testSendCommand(Device::find(3));
     *
     * @return array<string, mixed>
     */
    public function testSendCommand(Device $device): array
    {
        $integration = $this->resolveIntegration($device);

        return $this->customerRequest(
            integration: $integration,
            method: 'POST',
            path: "/v1.1/m/thing/{$device->external_device_id}/commands",
            params: null,
            body: ['commands' => [['code' => 'lock_motor_state', 'value' => false]]],
        );
    }

    /**
     * Probe temporário: testa vários paths de comando para descobrir o endpoint correto (404 → alternativas).
     * Tinker: (new TuyaIntegrationService)->probeCommandEndpoint(Device::find(3));
     */
    public function probeCommandEndpoint(Device $device): void
    {
        $integration = $this->resolveIntegration($device);
        $id = $device->external_device_id;

        $endpoints = [
            "/v1.1/m/life/{$id}/commands",
            "/v1.0/m/life/{$id}/commands",
            "/v1.0/m/life/ha/devices/{$id}/commands",
            "/v1.0/m/thing/{$id}/commands",
            "/v1.1/m/thing/devices/{$id}/commands",
        ];

        $body = ['commands' => [['code' => 'lock_motor_state', 'value' => false]]];

        foreach ($endpoints as $path) {
            $result = $this->customerRequest(
                integration: $integration,
                method: 'POST',
                path: $path,
                params: null,
                body: $body,
            );
            Log::info('[Tuya probe] '.$path, ['result' => $result]);
        }
    }

    /**
     * Cria senha temporária na fechadura Tuya via DP temporary_password_creat (apigw.iotbing.com).
     * Retorna a referência "tuyaSeq:serverSeq" para uso em deleteTemporaryPassword.
     *
     * @throws RuntimeException
     */
    public function createTemporaryPasswordViaDP(
        Device $device,
        string $pin,
        int $effectiveTime,
        int $invalidTime
    ): string {
        if (strlen($pin) !== 6 || ! ctype_digit($pin)) {
            throw new RuntimeException('PIN deve ter exatamente 6 digitos.');
        }

        $integration = $this->resolveIntegration($device);
        $tuyaSeq = random_int(0, 65535);
        $serverSeq = random_int(0, 65535);
        $lockId = 0x0000;

        $bytes = pack('n', $tuyaSeq)
            .pack('n', $serverSeq)
            .pack('n', $lockId)
            .pack('N', $effectiveTime)
            .pack('N', $invalidTime)
            .chr(0x00)
            .$pin;

        $value = base64_encode($bytes);

        $path = "/v1.1/m/thing/{$device->external_device_id}/commands";
        $body = [
            'commands' => [
                [
                    'code' => 'temporary_password_creat',
                    'value' => $value,
                ],
            ],
        ];

        $result = $this->customerRequest(
            integration: $integration,
            method: 'POST',
            path: $path,
            params: null,
            body: $body,
        );

        if ($result === []) {
            Log::error('[Tuya] createTemporaryPasswordViaDP: customerRequest retornou vazio', [
                'device_id' => $device->id,
                'external_device_id' => $device->external_device_id,
            ]);
            throw new RuntimeException('Tuya recusou a criacao do PIN temporario.');
        }

        Log::info('[Tuya] PIN temporario criado na fechadura via DP', [
            'device_id' => $device->id,
            'external_device_id' => $device->external_device_id,
        ]);

        return "{$tuyaSeq}:{$serverSeq}";
    }

    /**
     * Remove senha temporária na fechadura Tuya via DP temporary_password_delete.
     * $externalReference deve ser "tuyaSeq:serverSeq" retornado por createTemporaryPasswordViaDP; caso contrario usa zeros.
     */
    public function deleteTemporaryPassword(Device $device, string $externalReference): bool
    {
        $tuyaSeq = 0;
        $serverSeq = 0;
        $parts = explode(':', $externalReference, 2);
        if (count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
            $tuyaSeq = (int) $parts[0];
            $serverSeq = (int) $parts[1];
        }

        $lockId = 0x0000;
        $bytes = pack('n', $tuyaSeq).pack('n', $serverSeq).pack('n', $lockId);
        $value = base64_encode($bytes);

        $integration = $this->resolveIntegration($device);
        $path = "/v1.1/m/thing/{$device->external_device_id}/commands";
        $body = [
            'commands' => [
                [
                    'code' => 'temporary_password_delete',
                    'value' => $value,
                ],
            ],
        ];

        $result = $this->customerRequest(
            integration: $integration,
            method: 'POST',
            path: $path,
            params: null,
            body: $body,
        );

        if ($result !== []) {
            return true;
        }

        Log::warning('[Tuya] Falha ao deletar senha temporaria via DP', [
            'device_id' => $device->id,
            'external_device_id' => $device->external_device_id,
            'external_reference' => $externalReference,
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
        );

        return is_array($result) ? $result : [];
    }
}
