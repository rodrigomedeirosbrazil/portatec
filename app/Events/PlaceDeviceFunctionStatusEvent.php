<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaceDeviceFunctionStatusEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $placeId,
        public int $deviceId,
        public string $pin,
        public mixed $status,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("Place.Device.Function.Status.{$this->placeId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PlaceDeviceFunctionStatus';
    }
}
