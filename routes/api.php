<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SyncController;

Route::post('/sync/{chipId}', [SyncController::class, 'sync']);
