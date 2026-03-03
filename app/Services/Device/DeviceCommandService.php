<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\Enums\DeviceTypeEnum;
use App\Events\PlaceDeviceCommandAckEvent;
use App\Events\PlaceDeviceFunctionStatusEvent;
use App\Events\PlaceDeviceStatusEvent;
use App\Models\AccessCode;
use App\Models\AccessEvent;
use App\Models\CommandLog;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Services\DeviceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class DeviceCommandService
{
    public function __construct(
        private DeviceService $deviceService
    ) {}

    public function sendCommand(Device $device, string $action, int $pin, ?int $userId = null): string
    {
        if (! $device->external_device_id) {
            throw new \InvalidArgumentException('Device does not have external_device_id.');
        }

        $commandId = (string) Str::uuid();
        $payload = [
            'command_id' => $commandId,
            'action' => $action,
            'pin' => $pin,
            'timestamp' => time(),
        ];

        $mqtt = MQTT::connection();
        $mqtt->publish(
            topic: "device/{$device->external_device_id}/command",
            message: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            qualityOfService: 1
        );
        $mqtt->disconnect();

        $placeId = $device->place_id
            ?? $device->placeDeviceFunctions()->value('place_id');

        if ($placeId === null) {
            return $commandId;
        }

        CommandLog::create([
            'command_id' => $commandId,
            'user_id' => $userId,
            'place_id' => $placeId,
            'device_function_id' => $device->deviceFunctions()->where('pin', (string) $pin)->value('id'),
            'command_type' => $action,
            'command_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'device_function_type' => $device->deviceFunctions()->where('pin', (string) $pin)->value('type'),
        ]);

        return $commandId;
    }

    public function syncAccessCodes(Device $device, Collection $accessCodes): void
    {
        if (! $device->external_device_id) {
            return;
        }

        $payload = [
            'command_id' => (string) Str::uuid(),
            'action' => 'sync_access_codes',
            'default_pin' => $device->default_pin,
            'access_codes' => $accessCodes->map(fn (AccessCode $accessCode): array => [
                'pin' => $accessCode->pin,
                'start' => $accessCode->start->toIso8601String(),
                'end' => $accessCode->end?->toIso8601String(),
            ])->values()->all(),
            'timestamp' => time(),
        ];

        $mqtt = MQTT::connection();
        $mqtt->publish(
            topic: "device/{$device->external_device_id}/access-codes/sync",
            message: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            qualityOfService: 1
        );
        $mqtt->disconnect();
    }

    public function handleAck(string $chipId, array $payload): void
    {
        Log::info('MQTT ack received', ['chip_id' => $chipId, 'payload' => $payload]);

        $device = $this->resolveDeviceByChipId($chipId);
        if (! $device) {
            Log::warning('MQTT ack: device not found', ['chip_id' => $chipId, 'payload' => $payload]);

            return;
        }

        $commandId = data_get($payload, 'command_id');
        if ($commandId !== null) {
            CommandLog::query()
                ->where('command_id', (string) $commandId)
                ->update(['acknowledged_at' => now()]);
        }

        $pin = (string) data_get($payload, 'pin', '');
        $command = (string) data_get($payload, 'action', data_get($payload, 'command', 'ack'));

        $deviceFunction = $pin !== ''
            ? $device->deviceFunctions()->where('pin', $pin)->first()
            : null;

        if ($deviceFunction) {
            $this->dispatchAckToPlaces($device, $deviceFunction, $command, $commandId !== null ? (string) $commandId : null);

            return;
        }

        $device->deviceFunctions->each(function (DeviceFunction $function) use ($device, $command, $commandId): void {
            $this->dispatchAckToPlaces($device, $function, $command, $commandId !== null ? (string) $commandId : null);
        });
    }

    public function handleStatus(string $chipId, array $payload): void
    {
        $device = $this->resolveDeviceByChipId($chipId);
        if (! $device) {
            Log::warning('MQTT status: device not found', ['chip_id' => $chipId, 'payload' => $payload]);

            return;
        }

        $this->deviceService->updateStatus($chipId, $payload);

        $device->refresh();
        $placeIds = $device->placeDeviceFunctions()->pluck('place_id')->unique();

        $pin = data_get($payload, 'pin') ?? data_get($payload, 'sensor-pin');
        if ($pin !== null) {
            $deviceFunction = $device->deviceFunctions()->where('pin', (string) $pin)->first();
            if ($deviceFunction && $deviceFunction->type === DeviceTypeEnum::Sensor && $deviceFunction->status !== null) {
                foreach ($placeIds as $placeId) {
                    PlaceDeviceFunctionStatusEvent::dispatch(
                        (int) $placeId,
                        $device->id,
                        (string) $pin,
                        $deviceFunction->status
                    );
                }
            }
        }

        foreach ($placeIds as $placeId) {
            PlaceDeviceStatusEvent::dispatch((int) $placeId, $device->id, $device->isAvailable());
        }
    }

    public function handlePulse(string $chipId, array $payload): void
    {
        $device = $this->resolveDeviceByChipId($chipId);
        if (! $device) {
            Log::warning('MQTT pulse: device not found', ['chip_id' => $chipId, 'payload' => $payload]);

            return;
        }

        $device->forceFill(['last_sync' => now()])->save();

        $placeIds = $device->placeDeviceFunctions()->pluck('place_id')->unique();
        foreach ($placeIds as $placeId) {
            PlaceDeviceStatusEvent::dispatch((int) $placeId, $device->id, $device->isAvailable());
        }

        Log::info('MQTT pulse received', [
            'chip_id' => $chipId,
            'payload' => $payload,
        ]);
    }

    public function handleAccessEvent(string $chipId, array $payload): void
    {
        $device = $this->resolveDeviceByChipId($chipId);
        if (! $device) {
            Log::warning('MQTT event: device not found', ['chip_id' => $chipId, 'payload' => $payload]);

            return;
        }

        $pin = (string) data_get($payload, 'pin', data_get($payload, 'default_pin', ''));
        $result = $this->normalizeAccessResult(data_get($payload, 'result', 'invalid'));
        $placeId = $device->place_id ?? $device->placeDeviceFunctions()->value('place_id');

        $accessCode = null;
        if ($placeId !== null && $pin !== '') {
            $accessCode = AccessCode::query()
                ->where('place_id', $placeId)
                ->where('pin', $pin)
                ->first();
        }

        try {
            $accessEvent = AccessEvent::create([
                'device_id' => $device->id,
                'access_code_id' => $accessCode?->id,
                'pin' => $pin,
                'result' => $result,
                'device_timestamp' => data_get($payload, 'timestamp_device')
                    ? Carbon::createFromTimestamp((int) data_get($payload, 'timestamp_device'))
                    : null,
                'metadata' => $payload,
            ]);
            Log::info('MQTT access event recorded', [
                'chip_id' => $chipId,
                'access_event_id' => $accessEvent->id,
                'pin' => $pin,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('MQTT access event failed to create', [
                'chip_id' => $chipId,
                'payload' => $payload,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Normalize device result to allowed enum values: success, failed, expired, invalid.
     */
    private function normalizeAccessResult(mixed $result): string
    {
        $value = strtolower((string) $result);
        $map = [
            'ok' => 'success',
            'granted' => 'success',
            'valid' => 'success',
            '1' => 'success',
            'true' => 'success',
            'denied' => 'failed',
            '0' => 'failed',
            'false' => 'failed',
        ];

        return $map[$value] ?? (in_array($value, ['success', 'failed', 'expired', 'invalid'], true) ? $value : 'invalid');
    }

    /**
     * Resolve device by chip ID from MQTT topic. Accepts both "37feb9" and "esp-37feb9" formats
     * to support ESP devices that use "esp-{id}" as their MQTT client id.
     */
    private function resolveDeviceByChipId(string $chipId): ?Device
    {
        $device = Device::query()->where('external_device_id', $chipId)->first();
        if ($device) {
            return $device;
        }

        if (str_starts_with($chipId, 'esp-')) {
            return Device::query()->where('external_device_id', substr($chipId, 4))->first();
        }

        return Device::query()->where('external_device_id', 'esp-'.$chipId)->first();
    }

    private function dispatchAckToPlaces(Device $device, DeviceFunction $deviceFunction, string $command, ?string $commandId = null): void
    {
        $placeIds = $deviceFunction->placeDeviceFunctions->pluck('place_id')->unique();

        if ($placeIds->isEmpty() && $device->place_id !== null) {
            $placeIds = collect([$device->place_id]);
        }

        if ($placeIds->isEmpty()) {
            Log::warning('MQTT ack: no place to broadcast', [
                'device_id' => $device->id,
                'device_function_id' => $deviceFunction->id,
                'chip_id' => $device->external_device_id,
            ]);

            return;
        }

        foreach ($placeIds as $placeId) {
            PlaceDeviceCommandAckEvent::dispatch(
                (int) $placeId,
                $device->id,
                $command,
                (int) $deviceFunction->pin,
                (string) $deviceFunction->type->value,
                $commandId,
            );

            Log::info('MQTT ack: PlaceDeviceCommandAckEvent dispatched', [
                'place_id' => $placeId,
                'device_id' => $device->id,
                'pin' => $deviceFunction->pin,
                'command_id' => $commandId,
            ]);
        }
    }
}
