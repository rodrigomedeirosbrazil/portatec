<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\AccessCodeEvent;
use App\Models\AccessCode;

class AccessCodeObserver
{
    /**
     * Handle the AccessCode "created" event.
     */
    public function created(AccessCode $accessCode): void
    {
        AccessCodeEvent::dispatch($accessCode, 'create');
    }

    /**
     * Handle the AccessCode "updated" event.
     */
    public function updated(AccessCode $accessCode): void
    {
        AccessCodeEvent::dispatch($accessCode, 'update');
    }

    /**
     * Handle the AccessCode "deleted" event.
     */
    public function deleted(AccessCode $accessCode): void
    {
        AccessCodeEvent::dispatch($accessCode, 'delete');
    }

    /**
     * Handle the AccessCode "restored" event.
     */
    public function restored(AccessCode $accessCode): void
    {
        //
    }

    /**
     * Handle the AccessCode "force deleted" event.
     */
    public function forceDeleted(AccessCode $accessCode): void
    {
        //
    }
}
