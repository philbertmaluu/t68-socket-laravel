<?php

use App\Domains\CounterType\Controllers\CounterTypeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('counter-types', CounterTypeController::class);
