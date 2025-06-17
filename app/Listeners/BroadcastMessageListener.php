<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PlaceDeviceCommandAckEvent;
use App\Models\Device;
use App\Services\DeviceService;
use Laravel\Reverb\Events\MessageReceived;
use Illuminate\Support\Facades\Log;

class BroadcastMessageListener
{
    public function __construct(
        private DeviceService $deviceService
    ) {}

    public function handle(MessageReceived $event): void
    {
        $message = json_decode($event->message, true);

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

        $device = Device::where('chip_id', $data['chip-id'])->firstOrFail();

        $device->placeDevices
            ->pluck('place_id')
            ->unique()
            ->each(fn ($placeId) =>
                PlaceDeviceCommandAckEvent::dispatch(
                    placeId: $placeId,
                    deviceId: $device->id,
                    command: $data['command'],
                )
            );
    }
}
