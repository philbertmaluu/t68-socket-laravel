<?php

use Illuminate\Support\Facades\Route;

// Sanctum authentication routes
Route::post('/auth/login', function () {
    // TODO: Implement login logic
    return response()->json(['message' => 'Login endpoint - to be implemented']);
});

Route::post('/auth/logout', function () {
    // TODO: Implement logout logic
    return response()->json(['message' => 'Logout endpoint - to be implemented']);
})->middleware('auth:sanctum');

// Protected API routes with QMS prefix
Route::prefix('qms')->middleware('auth:sanctum')->group(function () {
    // Domain routes
    require app_path('Domains/Tenant/routes.php');
    require app_path('Domains/Counter/routes.php');
    require app_path('Domains/CounterType/routes.php');
    require app_path('Domains/Service/routes.php');
    require app_path('Domains/Service/ServiceDocument/routes.php');
    require app_path('Domains/Device/routes.php');
    require app_path('Domains/Ticket/routes.php');
});
