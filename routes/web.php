<?php

use App\Http\Controllers\Admin\StartImpersonationController;
use App\Http\Controllers\App\StopImpersonationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\TuyaIntegrationController;
use App\Livewire\AccessCodes\Create as CreateAccessCode;
use App\Livewire\AccessCodes\Edit as EditAccessCode;
use App\Livewire\AccessCodes\Index as IndexAccessCodes;
use App\Livewire\Bookings\Create as CreateBooking;
use App\Livewire\Bookings\Index as IndexBookings;
use App\Livewire\Bookings\Show as ShowBooking;
use App\Livewire\Dashboard;
use App\Livewire\Devices\Control as ControlDevice;
use App\Livewire\Devices\Create as CreateDevice;
use App\Livewire\Devices\Edit as EditDevice;
use App\Livewire\Devices\Index as IndexDevices;
use App\Livewire\Devices\Show as ShowDevice;
use App\Livewire\Integrations\Create as CreateIntegration;
use App\Livewire\Integrations\Edit as EditIntegration;
use App\Livewire\Integrations\Index as IndexIntegrations;
use App\Livewire\Places\AttachDevice as AttachDeviceToPlace;
use App\Livewire\Places\ClonePlace;
use App\Livewire\Places\Control as ControlPlace;
use App\Livewire\Places\Create as CreatePlace;
use App\Livewire\Places\Edit as EditPlace;
use App\Livewire\Places\Index as IndexPlaces;
use App\Livewire\Places\Members as MembersPlace;
use App\Livewire\Places\Show as ShowPlace;
use App\Models\ImpersonationSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app/login');
Route::redirect('/login', '/app/login');

Route::middleware('guest')->prefix('app')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('/app/logout', function (Request $request) {
    if ($request->session()->has('impersonation_session_id')) {
        ImpersonationSession::query()
            ->whereKey((int) $request->session()->get('impersonation_session_id'))
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
                'ended_ip' => $request->ip(),
                'ended_user_agent' => (string) $request->userAgent(),
            ]);
    }

    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/app/login');
})->name('logout');

Route::middleware('auth')->get('/admin/impersonations/{user}/start', StartImpersonationController::class)
    ->name('admin.impersonations.start');

Route::middleware('auth')
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::redirect('/', '/app/dashboard');
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        Route::get('/places', IndexPlaces::class)->name('places.index');
        Route::get('/places/create', CreatePlace::class)->name('places.create');
        Route::get('/places/{place}/devices/attach', AttachDeviceToPlace::class)->name('places.devices.attach');
        Route::get('/places/{place}/members', MembersPlace::class)->name('places.members');
        Route::get('/places/{place}/clone', ClonePlace::class)->name('places.clone');
        Route::get('/places/{place}', ShowPlace::class)->name('places.show');
        Route::get('/places/{place}/control', ControlPlace::class)->name('places.control');
        Route::get('/places/{place}/edit', EditPlace::class)->name('places.edit');

        Route::get('/bookings', IndexBookings::class)->name('bookings.index');
        Route::get('/bookings/create', CreateBooking::class)->name('bookings.create');
        Route::get('/bookings/{booking}', ShowBooking::class)->name('bookings.show');

        Route::get('/access-codes', IndexAccessCodes::class)->name('access-codes.index');
        Route::get('/access-codes/create', CreateAccessCode::class)->name('access-codes.create');
        Route::get('/access-codes/{accessCode}/edit', EditAccessCode::class)->name('access-codes.edit');

        Route::get('/devices', IndexDevices::class)->name('devices.index');
        Route::get('/devices/create', CreateDevice::class)->name('devices.create');
        Route::get('/devices/{device}/edit', EditDevice::class)->name('devices.edit');
        Route::get('/devices/{device}', ShowDevice::class)->name('devices.show');
        Route::get('/devices/{device}/control', ControlDevice::class)->name('devices.control');

        Route::get('/integrations', IndexIntegrations::class)->name('integrations.index');
        Route::get('/integrations/create', CreateIntegration::class)->name('integrations.create');
        Route::get('/integrations/{integration}/edit', EditIntegration::class)->name('integrations.edit');

        Route::get('/tuya/connect', [TuyaIntegrationController::class, 'showQRCode'])->name('tuya.connect');
        Route::post('/tuya/connect', [TuyaIntegrationController::class, 'startConnect'])->name('tuya.connect.start');
        Route::get('/tuya/poll/{token}', [TuyaIntegrationController::class, 'pollLogin'])->name('tuya.poll');
        Route::get('/tuya/devices', [TuyaIntegrationController::class, 'listDevices'])->name('tuya.devices');
        Route::post('/tuya/devices/assign-place', [TuyaIntegrationController::class, 'assignDeviceToPlace'])->name('tuya.devices.assign-place');
        Route::post('/tuya/devices/enable', [TuyaIntegrationController::class, 'enableDevice'])->name('tuya.devices.enable');
        Route::post('/tuya/command/{deviceId}', [TuyaIntegrationController::class, 'sendCommand'])->name('tuya.command');
        Route::delete('/tuya/disconnect', [TuyaIntegrationController::class, 'disconnect'])->name('tuya.disconnect');

        Route::post('/impersonations/stop', StopImpersonationController::class)->name('impersonations.stop');
    });
