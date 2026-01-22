<?php

use App\Domains\Service\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::apiResource('services', ServiceController::class);
