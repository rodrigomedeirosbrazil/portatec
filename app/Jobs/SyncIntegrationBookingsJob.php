<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Integration;
use App\Services\ICalSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncIntegrationBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $integrationId
    ) {}

    public function handle(ICalSyncService $syncService): void
    {
        $integration = Integration::find($this->integrationId);
        if (! $integration) {
            Log::warning('Integration not found for sync job', ['integration_id' => $this->integrationId]);

            return;
        }

        // Verificar se a Integration tem Places relacionados
        if ($integration->places->isEmpty()) {
            Log::warning("Integration {$this->integrationId} has no related places");

            return;
        }

        // Para cada Place relacionado à Integration
        foreach ($integration->places as $place) {
            try {
                $syncService->syncPlaceIntegration($place->id, $integration->id);
            } catch (\Throwable $e) {
                Log::error('Failed to sync bookings for integration place', [
                    'place_id' => $place->id,
                    'integration_id' => $integration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
