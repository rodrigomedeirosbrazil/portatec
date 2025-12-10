<?php

namespace App\Providers;

use App\Models\AccessPin;
use App\Models\Device;
use App\Models\Place;
use App\Models\User;
use App\Policies\AccessPinPolicy;
use App\Policies\DevicePolicy;
use App\Policies\PlacePolicy;
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
        AccessPin::class => AccessPinPolicy::class,
        Place::class => PlacePolicy::class,
        Device::class => DevicePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
