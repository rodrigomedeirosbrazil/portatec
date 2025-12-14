<?php

use App\Livewire\PlaceDeviceControl;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/main/login');

Route::get('/places/{place}/devices', PlaceDeviceControl::class)
    ->middleware(['auth'])
    ->name('places.devices');
