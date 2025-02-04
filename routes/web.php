<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/main/login');

Route::get('/place/{id}', App\Filament\Pages\PlacePage::class)
    ->name('place');
