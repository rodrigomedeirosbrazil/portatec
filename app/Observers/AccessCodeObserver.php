<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\AccessCodeEvent;
use App\Models\AccessCode;
use App\Services\AccessCodeSyncService;

class AccessCodeObserver
{
    public function __construct(
        private AccessCodeSyncService $syncService
    ) {}

    /**
     * Handle the AccessCode "created" event.
     */
    public function created(AccessCode $accessCode): void
    {
        // Dispara evento para notificar UI (manter comportamento existente)
        AccessCodeEvent::dispatch(
            $accessCode->attributesToArray(),
            'create',
            $accessCode->place_id
        );

        // Sincroniza com dispositivos
        $this->syncService->syncNewAccessCode($accessCode);
    }

    /**
     * Handle the AccessCode "updated" event.
     */
    public function updated(AccessCode $accessCode): void
    {
        // Dispara evento para notificar UI (manter comportamento existente)
        AccessCodeEvent::dispatch(
            $accessCode->attributesToArray(),
            'update',
            $accessCode->place_id
        );

        // Sincroniza com dispositivos
        $this->syncService->syncUpdatedAccessCode($accessCode);
    }

    /**
     * Handle the AccessCode "deleted" event.
     */
    public function deleted(AccessCode $accessCode): void
    {
        // Dispara evento para notificar UI (manter comportamento existente)
        AccessCodeEvent::dispatch(
            $accessCode->attributesToArray(),
            'delete',
            $accessCode->place_id
        );

        // Remove dos dispositivos
        $this->syncService->syncDeletedAccessCode($accessCode);
    }

    /**
     * Handle the AccessCode "restored" event.
     */
    public function restored(AccessCode $accessCode): void
    {
        // Quando restaurado, sincronizar novamente
        $this->syncService->syncNewAccessCode($accessCode);
    }

    /**
     * Handle the AccessCode "force deleted" event.
     */
    public function forceDeleted(AccessCode $accessCode): void
    {
        // Remove dos dispositivos mesmo no force delete
        $this->syncService->syncDeletedAccessCode($accessCode);
    }
}
