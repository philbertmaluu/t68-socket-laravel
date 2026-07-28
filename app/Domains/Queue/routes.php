<?php

use App\Domains\Queue\Controllers\QueueController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('queues', [QueueController::class, 'index']);
    Route::get('queues/{id}/activities', [QueueController::class, 'activities']);
    Route::get('queues/{id}/activities/tickets', [QueueController::class, 'activityTickets']);
    Route::get('queues/{id}/activities/export', [QueueController::class, 'exportActivityTickets']);
});
