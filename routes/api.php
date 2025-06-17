<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceController;

Route::get('/firmware', [DeviceController::class, 'updateFirmware']);
