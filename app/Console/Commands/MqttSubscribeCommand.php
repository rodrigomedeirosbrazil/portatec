<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Device\DeviceCommandService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;

class MqttSubscribeCommand extends Command
{
    protected $signature = 'mqtt:subscribe {--once : Process one loop iteration}';

    protected $description = 'Subscribe to MQTT device topics (ack, pulse, event).';

    public function handle(DeviceCommandService $service): int
    {
        $mqtt = MQTT::connection();

        $this->subscribe($mqtt, 'device/+/ack', function (string $chipId, array $payload) use ($service): void {
            $service->handleAck($chipId, $payload);
        });

        $this->subscribe($mqtt, 'device/+/pulse', function (string $chipId, array $payload) use ($service): void {
            $service->handlePulse($chipId, $payload);
        });

        $this->subscribe($mqtt, 'device/+/status', function (string $chipId, array $payload) use ($service): void {
            $service->handlePulse($chipId, $payload);
        });

        $this->subscribe($mqtt, 'device/+/event', function (string $chipId, array $payload) use ($service): void {
            $service->handleAccessEvent($chipId, $payload);
        });

        $this->subscribe($mqtt, 'device/+/access-codes/ack', function (string $chipId, array $payload): void {
            Log::info('MQTT access-codes ack received', ['chip_id' => $chipId, 'payload' => $payload]);
        });

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn () => $mqtt->interrupt());
        pcntl_signal(SIGTERM, fn () => $mqtt->interrupt());

        $mqtt->loop(! $this->option('once'));
        $mqtt->disconnect();

        return self::SUCCESS;
    }

    private function subscribe($mqtt, string $topic, callable $handler): void
    {
        $mqtt->subscribe($topic, function (string $topic, string $message) use ($handler): void {
            $payload = json_decode($message, true);
            if (! is_array($payload)) {
                return;
            }

            $parts = explode('/', $topic);
            $chipId = $parts[1] ?? '';
            if ($chipId === '') {
                return;
            }

            $handler($chipId, $payload);
        }, 1);
    }
}
