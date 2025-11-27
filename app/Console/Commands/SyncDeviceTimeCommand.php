<?php

namespace App\Console\Commands;

use App\Events\DeviceTimeSyncEvent;
use App\Models\Device;
use Illuminate\Console\Command;

class SyncDeviceTimeCommand extends Command
{
    protected $signature = 'devices:sync-time';
    protected $description = 'Send datetime event to online devices';

    public function handle(): void
    {
        // Get devices online in the last 10 minutes (matching isAvailable logic)
        Device::where('last_sync', '>=', now()->subMinutes(10))
            ->chunk(100, function ($devices) {
                foreach ($devices as $device) {
                    DeviceTimeSyncEvent::dispatch($device->chip_id, now()->timestamp);
                }
            });
    }
}
