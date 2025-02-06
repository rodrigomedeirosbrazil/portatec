<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/main/login');

Route::get('/place/{id}/{token?}', App\Filament\Pages\PlacePage::class)
    ->name('place');
