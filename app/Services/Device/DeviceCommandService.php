<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\Events\PlaceDeviceCommandAckEvent;
use App\Events\PlaceDeviceStatusEvent;
use App\Models\AccessCode;
use App\Models\AccessEvent;
use App\Models\CommandLog;
use App\Models\Device;
use App\Models\DeviceFunction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class DeviceCommandService
{
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
            'timestamp' => now()->toIso8601String(),
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
            'timestamp' => now()->toIso8601String(),
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
        $device = Device::query()->where('external_device_id', $chipId)->first();
        if (! $device) {
            return;
        }

        $pin = (string) data_get($payload, 'pin', '');
        $command = (string) data_get($payload, 'action', data_get($payload, 'command', 'ack'));

        $deviceFunction = $pin !== ''
            ? $device->deviceFunctions()->where('pin', $pin)->first()
            : null;

        if ($deviceFunction) {
            $this->dispatchAckToPlaces($device, $deviceFunction, $command);

            return;
        }

        $device->deviceFunctions->each(function (DeviceFunction $function) use ($device, $command): void {
            $this->dispatchAckToPlaces($device, $function, $command);
        });
    }

    public function handlePulse(string $chipId, array $payload): void
    {
        $device = Device::query()->where('external_device_id', $chipId)->first();
        if (! $device) {
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
        $device = Device::query()->where('external_device_id', $chipId)->first();
        if (! $device) {
            return;
        }

        $pin = (string) data_get($payload, 'pin', '');
        $result = (string) data_get($payload, 'result', 'invalid');

        $accessCode = AccessCode::query()
            ->where('place_id', $device->place_id)
            ->where('pin', $pin)
            ->first();

        AccessEvent::create([
            'device_id' => $device->id,
            'access_code_id' => $accessCode?->id,
            'pin' => $pin,
            'result' => $result,
            'device_timestamp' => data_get($payload, 'timestamp_device')
                ? Carbon::createFromTimestamp((int) data_get($payload, 'timestamp_device'))
                : null,
            'metadata' => $payload,
        ]);
    }

    private function dispatchAckToPlaces(Device $device, DeviceFunction $deviceFunction, string $command): void
    {
        $placeIds = $deviceFunction->placeDeviceFunctions->pluck('place_id')->unique();

        foreach ($placeIds as $placeId) {
            PlaceDeviceCommandAckEvent::dispatch(
                (int) $placeId,
                $device->id,
                $command,
                (int) $deviceFunction->pin,
                (string) $deviceFunction->type->value,
            );
        }
    }
}
