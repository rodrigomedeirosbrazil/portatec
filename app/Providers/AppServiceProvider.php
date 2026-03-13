<?php

namespace App\Providers;

use App\Contracts\ICalParserInterface;
use App\Models\AccessCode;
use App\Models\Booking;
use App\Observers\AccessCodeObserver;
use App\Observers\BookingObserver;
use App\Services\ICalParser;
use App\Services\Tuya\Client as TuyaClient;
use App\Services\Tuya\TuyaService;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
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

        $this->app->bind(TuyaService::class, function () {
            return new TuyaService(TuyaClient::fromConfig());
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.tailwind');

        AccessCode::observe(AccessCodeObserver::class);
        Booking::observe(BookingObserver::class);

        // Register Filament Livewire components
        Livewire::component('filament.livewire.notifications', Notifications::class);

        // Corrige URLs malformadas (ex: /https:/admin/login) quando atrás de proxy reverso HTTPS
        $appUrl = config('app.url');
        if ($appUrl && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }
    }
}
