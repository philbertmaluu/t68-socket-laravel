<?php

use App\Domains\Service\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('services/catalog', [ServiceController::class, 'catalog']);
Route::apiResource('services', ServiceController::class);
