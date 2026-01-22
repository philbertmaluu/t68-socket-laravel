<?php

use App\Domains\Counter\Controllers\CounterController;
use Illuminate\Support\Facades\Route;

Route::apiResource('counters', CounterController::class);
