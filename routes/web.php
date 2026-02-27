<?php

use App\Livewire\Dashboard;
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
    });
