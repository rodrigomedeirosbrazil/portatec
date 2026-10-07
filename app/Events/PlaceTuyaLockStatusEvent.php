<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Status da fechadura Tuya mudou. Vai no mesmo canal do `PlaceDeviceStatusEvent`, já autorizado
 * em routes/channels.php.
 */
class PlaceTuyaLockStatusEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array{locked: bool|null, battery: int|null, alert: string|null, updated_at: string|null} $status */
    public function __construct(
        public int $placeId,
        public int $deviceId,
        public array $status,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("Place.Device.Status.{$this->placeId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PlaceTuyaLockStatus';
    }
}
