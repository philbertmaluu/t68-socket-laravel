<?php

use Illuminate\Support\Facades\Route;

// Authentication routes (public)
require app_path('Domains/Authentication/routes.php');

// Protected API routes
Route::prefix('qms')->middleware('auth:sanctum')->group(function () {
    // Domain routes
    require app_path('Domains/Tenant/routes.php');
    require app_path('Domains/Counter/routes.php');
    require app_path('Domains/CounterType/routes.php');
    require app_path('Domains/Service/routes.php');
    require app_path('Domains/Service/ServiceDocument/routes.php');
    require app_path('Domains/Device/routes.php');
    require app_path('Domains/Ticket/routes.php');
    require app_path('Domains/Audit/routes.php');
});
