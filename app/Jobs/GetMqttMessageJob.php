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

    public function __construct(public MqttClient $mqtt, public string $topic)
    {
    }

    public function handle(): void
    {
        $this->mqtt = MQTT::connection();
        $this->mqtt->subscribe($this->topic, function ($topic, $message) {
            MqttMessageEvent::dispatch($topic, $message);
            $this->mqtt->interrupt();
        });

        $this->mqtt->loop(true);
        $this->mqtt->disconnect();
    }
}
