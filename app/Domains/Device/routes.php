<?php

use App\Domains\Device\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

Route::post('devices/{device}/regenerate-key', [DeviceController::class, 'regenerateKey'])
    ->name('devices.regenerate-key');
Route::apiResource('devices', DeviceController::class);
