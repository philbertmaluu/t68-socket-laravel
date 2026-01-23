<?php

use App\Domains\Authentication\Controllers\AuthController;
use App\Domains\Authentication\Controllers\HandoverController;
use Illuminate\Support\Facades\Route;

Route::prefix('qms')->group(function () {
    Route::post('/auth/authenticate', [AuthController::class, 'authenticate']);
    Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
    Route::get('/auth/user-details', [AuthController::class, 'userDetails'])->middleware('auth:sanctum');
    Route::get('/auth/user-roles/{module}', [AuthController::class, 'userRoles'])->middleware('auth:sanctum');
    Route::get('/auth/transfer-roles', [AuthController::class, 'transferRoles'])->middleware('auth:sanctum');
    Route::get('/auth/module-access', [AuthController::class, 'moduleAccess'])->middleware('auth:sanctum');
});


// Handover routes
Route::prefix('qms')->middleware('auth:sanctum')->group(function () {
    Route::post('/auth/handover/initiate', [HandoverController::class, 'initiate']);
    Route::post('/auth/handover/{id}/complete', [HandoverController::class, 'complete']);
    Route::post('/auth/handover/{id}/cancel', [HandoverController::class, 'cancel']);
    Route::get('/auth/handover/active', [HandoverController::class, 'active']);
    Route::get('/auth/handover/history', [HandoverController::class, 'history']);
});
