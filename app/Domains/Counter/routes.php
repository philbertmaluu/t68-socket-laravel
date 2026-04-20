<?php

use App\Domains\Counter\Controllers\CounterController;
use Illuminate\Support\Facades\Route;

Route::get('counters/me', [CounterController::class, 'myCounter']);
Route::get('counters/clerks', [CounterController::class, 'getClerks']);
Route::apiResource('counters', CounterController::class);
