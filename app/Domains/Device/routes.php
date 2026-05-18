<?php

use App\Domains\Device\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

Route::get('devices/hrp/regions', [DeviceController::class, 'getRegionsFromHrp'])
    ->name('devices.hrp.regions');
Route::get('devices/hrp/regions/{region_id}/offices', [DeviceController::class, 'getOfficesFromHrp'])
    ->name('devices.hrp.offices');
Route::post('devices/{device}/regenerate-key', [DeviceController::class, 'regenerateKey'])
    ->name('devices.regenerate-key');
Route::apiResource('devices', DeviceController::class);
