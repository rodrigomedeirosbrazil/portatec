<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PlaceDeviceCommandAckEvent;
use App\Events\PlaceDeviceStatusEvent;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Services\DeviceService;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Events\MessageReceived;

class BroadcastMessageListener
{
    public function __construct(
        private DeviceService $deviceService
    ) {}

    public function handle(MessageReceived $event): void
    {
        $message = json_decode($event->message, true);

        Log::info('Received message', ['message' => $message]);

        if (! $message || ! isset($message['event']) || ! isset($message['data'])) {
            return;
        }

        if ($message['event'] === 'client-device-status') {
            $this->handleClientDeviceStatus($message['data']);

            return;
        }

        if ($message['event'] === 'client-command-ack') {
            $this->handleClientCommandAck($message['data']);

            return;
        }

        if ($message['event'] === 'client-sensor-status') {
            $this->handleClientSensorStatus($message['data']);

            return;
        }
    }

    public function handleClientDeviceStatus(array $data): void
    {
        if (! $data || ! isset($data['chip-id'])) {
            Log::warning('Client device status event received without chip-id', ['message' => $data]);

            return;
        }

        $this->deviceService->updateStatus($data['chip-id'], $data);
    }

    public function handleClientCommandAck(array $data): void
    {
        if (! $data || ! isset($data['chip-id'])) {
            Log::warning('Client device status event received without chip-id', ['message' => $data]);

            return;
        }

        $device = Device::query()
            ->where('chip_id', $data['chip-id'])
            ->firstOrFail();

        $deviceFunction = $device->deviceFunctions()
            ->where('pin', $data['pin'])
            ->first();

        if (isset($deviceFunction)) {
            $placeDeviceFunctions = $deviceFunction->placeDeviceFunctions;

            if ($placeDeviceFunctions && $placeDeviceFunctions->isNotEmpty()) {
                $placeDeviceFunctions
                    ->pluck('place_id')
                    ->unique()
                    ->each(fn (int $placeId) => PlaceDeviceCommandAckEvent::dispatch(
                        $placeId,
                        $device->id,
                        $data['command'],
                        $deviceFunction->pin,
                        $deviceFunction->type->value,
                    )
                    );
            }

            return;
        }

        $deviceFunctions = $device->deviceFunctions()
            ->get();

        $deviceFunctions->each(function (DeviceFunction $deviceFunction) use ($data, $device) {
            $placeDeviceFunctions = $deviceFunction->placeDeviceFunctions;

            if ($placeDeviceFunctions && $placeDeviceFunctions->isNotEmpty()) {
                $placeDeviceFunctions
                    ->pluck('place_id')
                    ->unique()
                    ->each(fn (int $placeId) => PlaceDeviceCommandAckEvent::dispatch(
                        $placeId,
                        $device->id,
                        $data['command'],
                        $deviceFunction->pin,
                        $deviceFunction->type->value,
                    )
                    );
            }
        });
    }

    public function handleClientSensorStatus(array $data): void
    {
        if (! $data || ! isset($data['chip-id']) || ! isset($data['pin']) || ! isset($data['value'])) {
            Log::warning('Client sensor status event received with missing data', ['message' => $data]);

            return;
        }

        $this->deviceService->updateStatus(
            $data['chip-id'],
            [
                'pin' => $data['pin'],
                'status' => $data['value'],
            ]
        );

        $device = Device::where('chip_id', $data['chip-id'])->firstOrFail();

        $deviceFunction = $device->deviceFunctions()
            ->where('pin', $data['pin'])
            ->first();

        if (isset($deviceFunction)) {
            $placeDeviceFunctions = $deviceFunction->placeDeviceFunctions;

            if ($placeDeviceFunctions && $placeDeviceFunctions->isNotEmpty()) {
                $placeDeviceFunctions
                    ->pluck('place_id')
                    ->unique()
                    ->each(fn (int $placeId) => PlaceDeviceStatusEvent::dispatch(
                        $placeId,
                        $device->id,
                        $device->isAvailable(),
                    ));
            }

            return;
        }

        $placeDeviceFunctions = $device->placeDeviceFunctions;

        if ($placeDeviceFunctions && $placeDeviceFunctions->isNotEmpty()) {
            $placeDeviceFunctions
                ->pluck('place_id')
                ->unique()
                ->each(fn (int $placeId) => PlaceDeviceStatusEvent::dispatch(
                    $placeId,
                    $device->id,
                    $device->isAvailable(),
                )
                );
        }
    }
}
