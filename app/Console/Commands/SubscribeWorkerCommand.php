<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class SubscribeWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:subscribe-worker
                            {topic : The topic to subscribe to}';

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
        $topic = $this->argument('topic');

        if (! $topic) {
            $this->error('Please provide a topic to subscribe to.');

            return;
        }

        $this->info("Subscribed to topic [$topic].");

        $mqtt = MQTT::connection();
        $mqtt->subscribe($topic, function (string $topic, string $message) {
            $this->info("Received QoS level 1 message on topic [$topic]: $message");
        }, 1);

        $mqtt->loop(true);
    }
}
