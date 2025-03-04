<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SubscribeWorkerTerminateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:subscribe-worker-terminate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Terminate the subscribe worker';

    protected string $pidCacheKey = 'mqtt-subscribe-worker.pid';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pid = cache()->get($this->pidCacheKey);
        if (! $pid) {
            $this->error('No worker to terminate.');

            return;
        }

        $this->info("Terminating the subscribe worker (PID: $pid)...");
        posix_kill($pid, SIGINT)
            ? $this->info('SIGINT sent to the worker.')
            : $this->error('Failed to send SIGINT to the worker.');
    }
}
