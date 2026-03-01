<?php

namespace App\Providers;

use App\Contracts\ICalParserInterface;
use App\Models\AccessCode;
use App\Models\Booking;
use App\Observers\AccessCodeObserver;
use App\Observers\BookingObserver;
use App\Services\ICalParser;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->bind(ICalParserInterface::class, ICalParser::class);
    }

    public function boot(): void
    {
        AccessCode::observe(AccessCodeObserver::class);
        Booking::observe(BookingObserver::class);

        // Register Filament Livewire components
        Livewire::component('filament.livewire.notifications', Notifications::class);
    }
}
