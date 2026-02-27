<?php

use App\Livewire\Dashboard;
use App\Livewire\AccessCodes\Create as CreateAccessCode;
use App\Livewire\AccessCodes\Edit as EditAccessCode;
use App\Livewire\AccessCodes\Index as IndexAccessCodes;
use App\Livewire\Bookings\Create as CreateBooking;
use App\Livewire\Bookings\Index as IndexBookings;
use App\Livewire\Bookings\Show as ShowBooking;
use App\Livewire\Integrations\Create as CreateIntegration;
use App\Livewire\Integrations\Index as IndexIntegrations;
use App\Livewire\Places\Create as CreatePlace;
use App\Livewire\Places\Edit as EditPlace;
use App\Livewire\Places\Index as IndexPlaces;
use App\Livewire\Places\Show as ShowPlace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login');
Route::get('/login', fn () => redirect('/admin/login'))->name('login');
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/admin/login');
})->name('logout');

Route::middleware('auth')
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::redirect('/', '/app/dashboard');
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        Route::get('/places', IndexPlaces::class)->name('places.index');
        Route::get('/places/create', CreatePlace::class)->name('places.create');
        Route::get('/places/{place}', ShowPlace::class)->name('places.show');
        Route::get('/places/{place}/edit', EditPlace::class)->name('places.edit');

        Route::get('/bookings', IndexBookings::class)->name('bookings.index');
        Route::get('/bookings/create', CreateBooking::class)->name('bookings.create');
        Route::get('/bookings/{booking}', ShowBooking::class)->name('bookings.show');

        Route::get('/access-codes', IndexAccessCodes::class)->name('access-codes.index');
        Route::get('/access-codes/create', CreateAccessCode::class)->name('access-codes.create');
        Route::get('/access-codes/{accessCode}/edit', EditAccessCode::class)->name('access-codes.edit');

        Route::get('/integrations', IndexIntegrations::class)->name('integrations.index');
        Route::get('/integrations/create', CreateIntegration::class)->name('integrations.create');
    });
