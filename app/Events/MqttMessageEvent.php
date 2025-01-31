<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MqttMessageEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $topic, public string $message)
    {
        //
    }
}
