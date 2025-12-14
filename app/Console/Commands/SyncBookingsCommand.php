<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncIntegrationBookingsJob;
use App\Models\Integration;
use App\Models\Platform;
use Illuminate\Console\Command;

class SyncBookingsCommand extends Command
{
    protected $signature = 'bookings:sync
                            {--platform= : Platform slug (e.g., airbnb, booking_com)}
                            {--integration= : Integration ID}';

    protected $description = 'Synchronize bookings from iCal for active integrations';

    public function handle(): int
    {
        $query = Integration::query()
            ->whereNull('deleted_at')
            ->with('places');

        if ($this->option('platform')) {
            $platform = Platform::where('slug', $this->option('platform'))->first();
            if (!$platform) {
                $this->error("Platform not found: {$this->option('platform')}");
                return Command::FAILURE;
            }
            $query->where('platform_id', $platform->id);
        }

        if ($this->option('integration')) {
            $query->where('id', $this->option('integration'));
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            $this->info('No active integrations found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$integrations->count()} integration(s) to sync.");

        foreach ($integrations as $integration) {
            SyncIntegrationBookingsJob::dispatch($integration->id);
            $this->line("Queued sync job for Integration ID: {$integration->id}");
        }

        $this->info('All sync jobs have been queued.');

        return Command::SUCCESS;
    }
}
