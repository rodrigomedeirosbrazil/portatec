<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Models\Device;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Canal de push da Tuya (MQTT). Port de tuya_sharing/mq.py e manager.py:on_message.
 * As mensagens chegam em JSON puro — não há criptografia nesta camada.
 */
class TuyaMqttService
{
    private const PROTOCOL_DEVICE_REPORT = 4;

    private const PROTOCOL_OTHER = 20;

    private const ONLINE_BIZ_CODES = ['online' => true, 'offline' => false];

    public function __construct(
        private readonly TuyaCustomerApiClient $client = new TuyaCustomerApiClient,
    ) {}

    /**
     * Credenciais e tópicos do broker MQTT da Tuya. Válidas por `expireTime` segundos.
     *
     * @return array<string, mixed>
     */
    public function config(Integration $integration): array
    {
        $result = $this->client->post(
            $integration,
            '/v1.0/m/life/ha/access/config',
            body: ['linkId' => 'portatec.'.Str::uuid()],
        );

        if (! is_array($result)) {
            return [];
        }

        return $result;
    }

    /** @param array<string, mixed> $message */
    public function handleMessage(array $message): void
    {
        $protocol = (int) ($message['protocol'] ?? 0);
        $data = $message['data'] ?? [];

        if (! is_array($data)) {
            return;
        }

        if ($protocol === self::PROTOCOL_DEVICE_REPORT) {
            $this->handleDeviceReport($data);

            return;
        }

        if ($protocol === self::PROTOCOL_OTHER) {
            $this->handleBizEvent($data);
        }
    }

    /** @param array<string, mixed> $data */
    private function handleDeviceReport(array $data): void
    {
        $device = $this->findDevice($data['devId'] ?? null);

        if ($device === null) {
            return;
        }

        $status = is_array($data['status'] ?? null) ? $data['status'] : [];

        $device->forceFill([
            'tuya_status_payload' => $status,
            'last_sync' => now(),
        ])->save();

        Log::info('[Tuya MQTT] status reportado', [
            'device_id' => $device->id,
            'codes' => collect($status)->pluck('code')->all(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function handleBizEvent(array $data): void
    {
        $bizCode = (string) ($data['bizCode'] ?? '');

        if (! array_key_exists($bizCode, self::ONLINE_BIZ_CODES)) {
            return;
        }

        $device = $this->findDevice(data_get($data, 'bizData.devId'));

        if ($device === null) {
            return;
        }

        $device->forceFill([
            'tuya_online' => self::ONLINE_BIZ_CODES[$bizCode],
            'last_sync' => now(),
        ])->save();
    }

    private function findDevice(mixed $externalId): ?Device
    {
        if (! is_string($externalId) || $externalId === '') {
            return null;
        }

        return Device::query()->where('external_device_id', $externalId)->first();
    }
}
