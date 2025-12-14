<?php

namespace App\Providers;

use App\Models\AccessCode;
use App\Models\AccessEvent;
use App\Models\Booking;
use App\Models\Device;
use App\Models\Integration;
use App\Models\Place;
use App\Models\Platform;
use App\Models\User;
use App\Policies\AccessCodePolicy;
use App\Policies\AccessEventPolicy;
use App\Policies\BookingPolicy;
use App\Policies\DevicePolicy;
use App\Policies\IntegrationPolicy;
use App\Policies\PlacePolicy;
use App\Policies\PlatformPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Place::class => PlacePolicy::class,
        Device::class => DevicePolicy::class,
        User::class => UserPolicy::class,
        AccessCode::class => AccessCodePolicy::class,
        Booking::class => BookingPolicy::class,
        Platform::class => PlatformPolicy::class,
        Integration::class => IntegrationPolicy::class,
        AccessEvent::class => AccessEventPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
