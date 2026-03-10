<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncIntegrationBookingsJob;
use App\Models\Integration;
use App\Models\Platform;
use App\Services\ICalSyncService;
use Illuminate\Console\Command;

class SyncBookingsCommand extends Command
{
    protected $signature = 'bookings:sync
                            {--platform= : Platform slug (e.g., airbnb, booking_com)}
                            {--integration= : Integration ID}
                            {--now : Run sync immediately without queue}';

    protected $description = 'Synchronize bookings from iCal for active integrations';

    public function handle(ICalSyncService $syncService): int
    {
        $query = Integration::query()
            ->whereNull('deleted_at')
            ->with('places');

        if ($this->option('platform')) {
            $platform = Platform::where('slug', $this->option('platform'))->first();
            if (! $platform) {
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
            if ($integration->places->isEmpty()) {
                $this->warn("Integration {$integration->id} has no related places.");

                continue;
            }

            foreach ($integration->places as $place) {
                if ($this->option('now')) {
                    $syncService->syncPlaceIntegration($place->id, $integration->id);
                    $this->line("Synced integration {$integration->id} for place {$place->id}");

                    continue;
                }

                SyncIntegrationBookingsJob::dispatch($integration->id, $place->id);
                $this->line("Queued sync job for Integration ID: {$integration->id} / Place ID: {$place->id}");
            }
        }

        $this->info($this->option('now') ? 'All syncs completed.' : 'All sync jobs have been queued.');

        return Command::SUCCESS;
    }
}
