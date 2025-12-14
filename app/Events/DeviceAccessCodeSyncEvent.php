<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AccessCode;
use App\Models\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DeviceAccessCodeSyncEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Device $device,
        public string $action, // 'sync', 'create', 'update', 'delete'
        public ?AccessCode $accessCode = null,
        public ?Collection $accessCodes = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("device-sync.{$this->device->external_device_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'server-access-codes-sync';
    }

    public function broadcastWith(): array
    {
        $data = [
            'action' => $this->action,
        ];

        if ($this->action === 'sync' && $this->accessCodes) {
            $data['access_codes'] = $this->accessCodes->map(function (AccessCode $accessCode) {
                return [
                    'pin' => $accessCode->pin,
                    'start' => $accessCode->start->toIso8601String(),
                    'end' => $accessCode->end->toIso8601String(),
                ];
            })->toArray();
        } elseif ($this->accessCode) {
            $data['access_code'] = [
                'pin' => $this->accessCode->pin,
                'start' => $this->accessCode->start->toIso8601String(),
                'end' => $this->accessCode->end->toIso8601String(),
            ];
        }

        return $data;
    }
}
