<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\PlaceDeviceFunctionStatusEvent;
use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTuyaWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly array $payload
    ) {}

    public function handle(): void
    {
        $dataId = $this->payload['dataId'] ?? null;
        $dataType = $this->payload['dataType'] ?? null;
        $data = $this->payload['data'] ?? [];

        $deviceId = $data['deviceId'] ?? $data['device_id'] ?? null;
        if ($deviceId === null) {
            return;
        }

        $device = Device::query()
            ->where('brand', 'tuya')
            ->where('external_device_id', $deviceId)
            ->first();

        if ($device === null) {
            return;
        }

        $device->update(['last_sync' => now()]);

        $status = $data['status'] ?? [];
        if ($dataType === 'deviceStatus' && is_array($status)) {
            foreach ($status as $item) {
                $code = $item['code'] ?? null;
                $value = $item['value'] ?? null;
                if ($code !== null && $value !== null) {
                    $this->dispatchStatusEvent($device, (string) $code, $value);
                }
            }
        }
    }

    private function dispatchStatusEvent(Device $device, string $code, mixed $value): void
    {
        $placeId = $device->place_id ?? $device->placeDeviceFunctions()->value('place_id');
        if ($placeId === null) {
            return;
        }

        $deviceFunction = $device->getStatusFunction();
        if ($deviceFunction !== null) {
            event(new PlaceDeviceFunctionStatusEvent(
                (int) $placeId,
                $device->id,
                (string) $deviceFunction->pin,
                (string) $value
            ));
        }
    }
}
