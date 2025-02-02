<?php

namespace App\Jobs;

use App\Events\MqttMessageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Facades\MQTT;

class GetMqttMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $topic)
    {
    }

    public function handle(): void
    {
        $mqtt = MQTT::connection();
        $mqtt->subscribe($this->topic, function ($topic, $message) use ($mqtt) {
            MqttMessageEvent::dispatch($topic, $message);
            $mqtt->interrupt();
        });

        $mqtt->loop(true);
        $mqtt->disconnect();
    }
}
