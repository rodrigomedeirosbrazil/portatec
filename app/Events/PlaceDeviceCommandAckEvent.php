<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaceDeviceCommandAckEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $placeId,
        public int $deviceId,
        public string $command,
        public int $pin,
        public string $type,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("Place.Device.Command.Ack.{$this->placeId}"),
        ];
    }
}
