<?php

namespace App\Console\Commands;

use App\Events\MqttMessageEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;

class SubscribeWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:subscribe-worker
                            {--topic=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to an MQTT topic and listen for messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $topic = $this->option('topic');

        if (! $topic) {
            $topic = '#';
        }

        $this->info("Subscribed to topic [$topic].");

        $mqtt = MQTT::connection();
        $mqtt->subscribe($topic, function (string $topic, string $message) {
            $this->info("$topic: $message");
            Log::channel('mqtt-messages')->info("$topic: $message");
            MqttMessageEvent::dispatch($topic, $message);
        }, 0);

        $mqtt->loop(true);
    }
}
