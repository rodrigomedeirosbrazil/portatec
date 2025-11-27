<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceTimeSyncEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $chipId,
        public int $timestamp
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("device-sync.{$this->chipId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'datetime';
    }

    public function broadcastWith(): array
    {
        return [
            'unix_time' => $this->timestamp,
        ];
    }
}
