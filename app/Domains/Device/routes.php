<?php

use App\Domains\Device\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

Route::apiResource('devices', DeviceController::class);
