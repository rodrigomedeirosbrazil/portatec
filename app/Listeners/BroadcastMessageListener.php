<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\DeviceSyncService;
use Laravel\Reverb\Events\MessageReceived;
use Illuminate\Support\Facades\Log;

class BroadcastMessageListener
{
    public function __construct(
        private DeviceSyncService $deviceSyncService
    ) {}

    public function handle(MessageReceived $event): void
    {
        $message = json_decode($event->message, true);

        if (! $message || ! isset($message['event']) || ! isset($message['data'])) {
            return;
        }

        // Check if this is a device sync event
        if ($message['event'] !== 'DeviceSync') {
            return;
        }

        $data = is_string($message['data']) ? json_decode($message['data'], true) : $message['data'];

        if (! $data || ! isset($data['chip_id'])) {
            Log::warning('Device sync event received without chip_id', ['message' => $message]);
            return;
        }

        try {
            $result = $this->deviceSyncService->syncDevice($data['chip_id'], $data);

            Log::info('Device synced via broadcast', [
                'chip_id' => $data['chip_id'],
                'has_command' => $result['has_command'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync device via broadcast', [
                'chip_id' => $data['chip_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
