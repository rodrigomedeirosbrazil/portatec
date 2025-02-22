<?php

namespace App\Listeners;

use App\Events\DeviceCreatedEvent;
use App\Events\DeviceDeletedEvent;
use App\Events\DeviceUpdatedEvent;
use Illuminate\Support\Facades\Artisan;

class SubscribeReloadDeviceChangeListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handleDeviceCreatedEvent(DeviceCreatedEvent $event): void
    {
        Artisan::call('mqtt:subscribe-worker-terminate');
    }

    public function handleDeviceUpdatedEvent(DeviceUpdatedEvent $event): void
    {
        if (
            array_key_exists('topic', $event->changes)
            || array_key_exists('availability_topic', $event->changes)
        ) {
            Artisan::call('mqtt:subscribe-worker-terminate');
        }

    }

    public function handleDeviceDeletedEvent(DeviceDeletedEvent $event): void
    {
        Artisan::call('mqtt:subscribe-worker-terminate');
    }
}
