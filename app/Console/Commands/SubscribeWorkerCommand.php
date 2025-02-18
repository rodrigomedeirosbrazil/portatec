<?php

namespace App\Console\Commands;

use App\Events\MqttMessageEvent;
use App\Models\Device;
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
    protected $signature = 'mqtt:subscribe-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to an MQTT topics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mqtt = MQTT::connection();

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn () => $mqtt->interrupt());

        Device::all()
            ->pluck('topic')
            ->unique()
            ->each(function (string $topic) use ($mqtt) {
                $mqtt->subscribe($topic, function (string $topic, string $message) {
                    $this->info("$topic: $message");
                    Log::channel('mqtt-messages')->info("$topic: $message");
                    MqttMessageEvent::dispatch($topic, $message);
                }, 0);
            });

        $mqtt->loop(true);
    }
}
