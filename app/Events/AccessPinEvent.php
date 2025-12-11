<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AccessPin;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccessPinEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AccessPin $accessPin,
        public string $action
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("place.{$this->accessPin->place_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'access-pin';
    }

    public function broadcastWith(): array
    {
        return [
            'accessPin' => $this->accessPin,
            'action' => $this->action,
        ];
    }
}
