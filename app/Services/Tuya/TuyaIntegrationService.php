<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Exceptions\TuyaApiException;
use App\Models\Device;
use App\Models\Integration;
use App\Services\Tuya\DTOs\TuyaDeviceDTO;
use Illuminate\Support\Collection;

class TuyaIntegrationService
{
    public function __construct(
        private readonly TuyaCustomerApiClient $client = new TuyaCustomerApiClient,
    ) {}

    public function listDevices(Integration $integration): Collection
    {
        $homes = $this->client->get($integration, '/v1.0/m/life/users/homes');

        if (! is_array($homes)) {
            return collect();
        }

        $devices = collect();

        foreach ($homes as $home) {
            $homeId = $home['ownerId'] ?? null;

            if ($homeId === null || $homeId === '') {
                continue;
            }

            $homeDevices = $this->client->get(
                $integration,
                '/v1.0/m/life/ha/home/devices',
                ['homeId' => (string) $homeId],
            );

            if (is_array($homeDevices)) {
                $devices = $devices->merge($homeDevices);
            }
        }

        return $devices
            ->map(fn (array $device): TuyaDeviceDTO => new TuyaDeviceDTO(
                id: (string) ($device['id'] ?? ''),
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

        $this->syncDeviceSpecifications($device);

        return $snapshot;
    }

    private const DP_CREATE = 'temporary_password_creat';

    private const DP_DELETE = 'temporary_password_delete';

    /**
     * DP 24 — cria senha temporária. Payload raw de 21 bytes em Base64.
     * https://developer.tuya.com/en/docs/iot/zigbee-doorlock-dp?id=K9fembhbeab0p
     *
     * Retorna "tuyaSeq:serverSeq", que identifica a senha para remoção posterior.
     *
     * @throws TuyaApiException
     */
    public function createTemporaryPassword(
        Device $device,
        string $pin,
        int $effectiveTime,
        int $invalidTime,
    ): string {
        if (strlen($pin) !== 6 || ! ctype_digit($pin)) {
            throw new TuyaApiException('PIN deve ter exatamente 6 dígitos.');
        }

        if (! $device->supportsTuyaTemporaryPassword()) {
            throw new TuyaApiException(
                "Dispositivo {$device->id} não declara o DP ".self::DP_CREATE.'.'
            );
        }

        $integration = $this->resolveIntegration($device);
        $tuyaSeq = random_int(0, 65535);
        $serverSeq = random_int(0, 65535);

        $this->client->post(
            $integration,
            "/v1.1/m/thing/{$device->external_device_id}/commands",
            body: ['commands' => [[
                'code' => self::DP_CREATE,
                'value' => $this->buildCreatePayload($tuyaSeq, $serverSeq, $pin, $effectiveTime, $invalidTime),
            ]]],
        );

        return "{$tuyaSeq}:{$serverSeq}";
    }

    /**
     * DP 25 — remove senha temporária. Payload raw de 6 bytes em Base64.
     *
     * @throws TuyaApiException
     */
    public function deleteTemporaryPassword(Device $device, string $externalReference): void
    {
        [$tuyaSeq, $serverSeq] = $this->parseReference($externalReference);

        $this->client->post(
            $this->resolveIntegration($device),
            "/v1.1/m/thing/{$device->external_device_id}/commands",
            body: ['commands' => [[
                'code' => self::DP_DELETE,
                'value' => $this->buildDeletePayload($tuyaSeq, $serverSeq),
            ]]],
        );
    }

    private function buildCreatePayload(
        int $tuyaSeq,
        int $serverSeq,
        string $pin,
        int $effectiveTime,
        int $invalidTime,
    ): string {
        return base64_encode(
            pack('n', $tuyaSeq)          // [0..1]  Tuya serial number
            .pack('n', $serverSeq)       // [2..3]  server serial number
            .pack('n', 0x0000)           // [4..5]  lock manufacturer id
            .pack('N', $effectiveTime)   // [6..9]  início, unix big-endian
            .pack('N', $invalidTime)     // [10..13] fim, unix big-endian
            .chr(0x00)                   // [14]    não é one-time
            .$pin                        // [15..20] 6 dígitos ASCII
        );
    }

    private function buildDeletePayload(int $tuyaSeq, int $serverSeq): string
    {
        return base64_encode(
            pack('n', $tuyaSeq)
            .pack('n', $serverSeq)
            .pack('n', 0x0000)
        );
    }

    /** @return array{0: int, 1: int} */
    private function parseReference(string $externalReference): array
    {
        $parts = explode(':', $externalReference, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            throw new TuyaApiException("Referência de senha temporária inválida: {$externalReference}");
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    /**
     * Busca os function codes suportados pelo dispositivo.
     * Port de device.py DeviceRepository.update_device_specification.
     *
     * @return list<string>
     */
    public function syncDeviceSpecifications(Device $device): array
    {
        $integration = $this->resolveIntegration($device);

        $result = $this->client->get(
            $integration,
            "/v1.1/m/life/{$device->external_device_id}/specifications",
        );

        if (! is_array($result) || ! is_array($result['functions'] ?? null)) {
            return [];
        }

        $codes = collect($result['functions'])
            ->pluck('code')
            ->filter(fn ($code): bool => is_string($code) && $code !== '')
            ->values()
            ->all();

        $device->forceFill(['tuya_functions' => $codes])->save();

        return $codes;
    }

    private function resolveIntegration(Device $device): Integration
    {
        $integration = $device->integration;
        if (! $integration instanceof Integration) {
            throw new TuyaApiException('Dispositivo Tuya sem integracao associada.');
        }

        return $integration;
    }
}
