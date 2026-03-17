<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Place;
use App\Services\AccessCodeSyncService;
use Illuminate\Console\Command;

class SyncAccessCodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'access-codes:sync
                            {--place= : Place ID}
                            {--device= : Device ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize access codes to devices';

    public function handle(AccessCodeSyncService $syncService): int
    {
        $placeId = $this->option('place');
        $deviceId = $this->option('device');

        if ($deviceId) {
            $device = Device::find($deviceId);
            if (! $device) {
                $this->error("Device not found: {$deviceId}");

                return Command::FAILURE;
            }

            $this->info("Syncing access codes for device: {$device->name} (ID: {$device->id})");
            $syncService->syncAccessCodesToDevice($device);
            $this->info('✅ Sync completed');

            return Command::SUCCESS;
        }

        if ($placeId) {
            $place = Place::find($placeId);
            if (! $place) {
                $this->error("Place not found: {$placeId}");

                return Command::FAILURE;
            }

            $devices = Device::query()
                ->where('place_id', $placeId)
                ->orWhereHas('places', fn ($query) => $query->where('places.id', $placeId))
                ->get();

            if ($devices->isEmpty()) {
                $this->warn("No devices found for place: {$place->name}");

                return Command::SUCCESS;
            }

            $this->info("Syncing access codes for place: {$place->name} (ID: {$place->id})");
            $this->info("Found {$devices->count()} device(s)");

            foreach ($devices as $device) {
                $syncService->syncAccessCodesToDevice($device);
                $this->line("  ✅ Synced device: {$device->name}");
            }

            $this->info('✅ Sync completed');

            return Command::SUCCESS;
        }

        // Sync all places
        $places = Place::has('devices')->get();

        if ($places->isEmpty()) {
            $this->info('No places with devices found.');

            return Command::SUCCESS;
        }

        $this->info("Syncing access codes for {$places->count()} place(s)");

        foreach ($places as $place) {
            $devices = $place->devices;

            foreach ($devices as $device) {
                $syncService->syncAccessCodesToDevice($device);
            }

            $this->line("  ✅ Synced place: {$place->name}");
        }

        $this->info('✅ Sync completed for all places');

        return Command::SUCCESS;
    }
}
