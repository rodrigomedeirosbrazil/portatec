<?php

use App\Http\Controllers\Admin\StartImpersonationController;
use App\Http\Controllers\App\DeviceCommandController;
use App\Http\Controllers\App\DeviceControlController;
use App\Http\Controllers\App\DeviceController;
use App\Http\Controllers\App\DeviceIntegrationController;
use App\Http\Controllers\App\PlaceAttachDeviceController;
use App\Http\Controllers\App\PlaceCloneController;
use App\Http\Controllers\App\PlaceControlController;
use App\Http\Controllers\App\PlaceController;
use App\Http\Controllers\App\PlaceDeviceController;
use App\Http\Controllers\App\PlaceMemberController;
use App\Http\Controllers\App\PlaceMemberSearchController;
use App\Http\Controllers\App\StopImpersonationController;
use App\Http\Controllers\App\TuyaConnectController;
use App\Http\Controllers\App\TuyaQrController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Livewire\AccessCodes\Create as CreateAccessCode;
use App\Livewire\AccessCodes\Edit as EditAccessCode;
use App\Livewire\AccessCodes\Index as IndexAccessCodes;
use App\Livewire\Bookings\Create as CreateBooking;
use App\Livewire\Bookings\Index as IndexBookings;
use App\Livewire\Bookings\Show as ShowBooking;
use App\Livewire\Dashboard;
use App\Livewire\Integrations\Create as CreateIntegration;
use App\Livewire\Integrations\Edit as EditIntegration;
use App\Livewire\Integrations\Index as IndexIntegrations;
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

        Route::get('/places', [PlaceController::class, 'index'])->name('places.index');
        Route::get('/places/create', [PlaceController::class, 'create'])->name('places.create');
        Route::post('/places', [PlaceController::class, 'store'])->name('places.store');
        Route::get('/places/{place}/devices/attach', [PlaceAttachDeviceController::class, 'create'])->name('places.devices.attach');
        Route::post('/places/{place}/devices/attach', [PlaceAttachDeviceController::class, 'store'])->name('places.devices.attach.store');
        Route::delete('/places/{place}/devices/{device}', [PlaceDeviceController::class, 'destroy'])->name('places.devices.destroy');
        Route::get('/places/{place}/members', [PlaceMemberController::class, 'index'])->name('places.members');
        Route::post('/places/{place}/members', [PlaceMemberController::class, 'store'])->name('places.members.store');
        Route::delete('/places/{place}/members/{placeUser}', [PlaceMemberController::class, 'destroy'])->name('places.members.destroy');
        Route::get('/places/{place}/members/search', PlaceMemberSearchController::class)->name('places.members.search');
        Route::get('/places/{place}/clone', [PlaceCloneController::class, 'create'])->name('places.clone');
        Route::post('/places/{place}/clone', [PlaceCloneController::class, 'store'])->name('places.clone.store');
        Route::get('/places/{place}', [PlaceController::class, 'show'])->name('places.show');
        Route::get('/places/{place}/control', [PlaceControlController::class, 'show'])->name('places.control');
        Route::post('/places/{place}/commands', [DeviceCommandController::class, 'store'])->name('places.commands.store');
        Route::get('/places/{place}/edit', [PlaceController::class, 'edit'])->name('places.edit');
        Route::put('/places/{place}', [PlaceController::class, 'update'])->name('places.update');

        Route::get('/bookings', IndexBookings::class)->name('bookings.index');
        Route::get('/bookings/integrations', IndexIntegrations::class)->name('bookings.integrations.index');
        Route::get('/bookings/integrations/create', CreateIntegration::class)->name('bookings.integrations.create');
        Route::get('/bookings/integrations/{integration}/edit', EditIntegration::class)->name('bookings.integrations.edit');
        Route::get('/bookings/create', CreateBooking::class)->name('bookings.create');
        Route::get('/bookings/{booking}', ShowBooking::class)->name('bookings.show');

        Route::get('/access-codes', IndexAccessCodes::class)->name('access-codes.index');
        Route::get('/access-codes/create', CreateAccessCode::class)->name('access-codes.create');
        Route::get('/access-codes/{accessCode}/edit', EditAccessCode::class)->name('access-codes.edit');

        Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::get('/devices/integrations', [DeviceIntegrationController::class, 'index'])->name('devices.integrations.index');
        Route::get('/devices/integrations/tuya-connect', [TuyaConnectController::class, 'create'])->name('devices.integrations.tuya-connect');
        Route::post('/devices/integrations/tuya-connect', [TuyaConnectController::class, 'store'])->name('devices.integrations.tuya.store');
        Route::post('/devices/integrations/tuya-connect/qr', [TuyaQrController::class, 'store'])->name('devices.integrations.tuya.qr.store');
        Route::get('/devices/integrations/tuya-connect/qr/poll', [TuyaQrController::class, 'show'])->name('devices.integrations.tuya.qr.poll');
        Route::delete('/devices/integrations/tuya-connect/qr', [TuyaQrController::class, 'destroy'])->name('devices.integrations.tuya.qr.destroy');
        Route::get('/devices/create', [DeviceController::class, 'create'])->name('devices.create');
        Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
        Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::get('/devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
        Route::get('/devices/{device}/control', [DeviceControlController::class, 'show'])->name('devices.control');
        Route::post('/devices/{device}/commands', [DeviceCommandController::class, 'storeForDevice'])->name('devices.commands.store');

        Route::redirect('/integrations', '/app/bookings/integrations');
        Route::redirect('/integrations/tuya-connect', '/app/devices/integrations/tuya-connect');
        Route::redirect('/integrations/create', '/app/bookings/integrations/create');
        Route::redirect('/integrations/{integration}/edit', '/app/bookings/integrations/{integration}/edit');

        Route::post('/impersonations/stop', StopImpersonationController::class)->name('impersonations.stop');
    });
