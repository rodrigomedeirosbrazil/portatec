<?php

namespace App\Providers;

use App\Events\MqttMessageEvent;
use App\Listeners\DeliveryStatusFromMqttListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Event::listen(
            MqttMessageEvent::class,
            DeliveryStatusFromMqttListener::class,
        );
    }
}
