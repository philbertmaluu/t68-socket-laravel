<?php

use App\Domains\Queue\Controllers\QueueController;
use Illuminate\Support\Facades\Route;

Route::get('queues', [QueueController::class, 'index'])->middleware('auth:sanctum');
