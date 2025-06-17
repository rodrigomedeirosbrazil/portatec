<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaceDeviceStatusEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $placeId,
        public int $deviceId,
        public bool $isAvailable,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("Place.Device.Status.{$this->placeId}"),
        ];
    }
}
