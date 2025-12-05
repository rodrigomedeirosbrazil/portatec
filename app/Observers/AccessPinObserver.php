<?php

namespace App\Observers;

use App\Events\AccessPinEvent;
use App\Models\AccessPin;

class AccessPinObserver
{
    /**
     * Handle the AccessPin "created" event.
     */
    public function created(AccessPin $accessPin): void
    {
        AccessPinEvent::dispatch($accessPin, 'create');
    }

    /**
     * Handle the AccessPin "updated" event.
     */
    public function updated(AccessPin $accessPin): void
    {
        AccessPinEvent::dispatch($accessPin, 'update');
    }

    /**
     * Handle the AccessPin "deleted" event.
     */
    public function deleted(AccessPin $accessPin): void
    {
        AccessPinEvent::dispatch($accessPin, 'delete');
    }

    /**
     * Handle the AccessPin "restored" event.
     */
    public function restored(AccessPin $accessPin): void
    {
        //
    }

    /**
     * Handle the AccessPin "force deleted" event.
     */
    public function forceDeleted(AccessPin $accessPin): void
    {
        //
    }
}
