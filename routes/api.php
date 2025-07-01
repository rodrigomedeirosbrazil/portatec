<?php

use App\Http\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

Route::get('/firmware', [DeviceController::class, 'updateFirmware']);
