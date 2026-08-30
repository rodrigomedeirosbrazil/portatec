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

    /**
     * Tópicos a assinar: o do home (eventos de vínculo, online/offline) e os dos
     * dispositivos (relatório de DP).
     *
     * O placeholder {ownerId} é o `ownerId` do home — não o uid do usuário.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function topicsFor(Integration $integration, array $config): array
    {
        $topics = [];

        $homeTemplate = (string) data_get($config, 'topic.ownerId.sub');
        if ($homeTemplate !== '') {
            foreach ($this->homeOwnerIds($integration) as $ownerId) {
                $topics[] = str_replace('{ownerId}', $ownerId, $homeTemplate);
            }
        }

        $deviceTemplate = (string) data_get($config, 'topic.devId.sub');
        if ($deviceTemplate !== '') {
            $externalIds = $integration->devices()
                ->whereNotNull('external_device_id')
                ->pluck('external_device_id');

            foreach ($externalIds as $externalId) {
                $base = str_replace('{devId}', (string) $externalId, $deviceTemplate);

                // O SDK escolhe /pen ou /sta conforme o supportLocal do dispositivo.
                // Assinar os dois evita uma chamada HTTP extra por dispositivo, e
                // assinar um tópico que não recebe nada é inofensivo.
                $topics[] = $base.'/pen';
                $topics[] = $base.'/sta';
            }
        }

        return array_values(array_unique($topics));
    }

    /** @return list<string> */
    private function homeOwnerIds(Integration $integration): array
    {
        $homes = $this->client->get($integration, '/v1.0/m/life/users/homes');

        if (! is_array($homes)) {
            return [];
        }

        return collect($homes)
            ->pluck('ownerId')
            ->filter()
            ->map(fn ($ownerId): string => (string) $ownerId)
            ->values()
            ->all();
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
