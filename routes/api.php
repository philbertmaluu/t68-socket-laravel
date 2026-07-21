<?php

use App\Domains\Device\Controllers\DeviceAuthController;
use App\Domains\Ictms\Controllers\IctmsAccessController;
use App\Domains\Ictms\Controllers\IctmsMonitoringController;
use App\Domains\Service\Controllers\PublicServiceController;
use App\Domains\Ticket\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

// ICTMS Access Management (called by ICTMS server; no auth)
Route::get('modules', [IctmsAccessController::class, 'modules']);
Route::post('module/roles', [IctmsAccessController::class, 'moduleRoles']);
Route::post('assign-role', [IctmsAccessController::class, 'assignRole']);
Route::post('assign-roles', [IctmsAccessController::class, 'assignRoles']);
Route::get('user/roles', [IctmsAccessController::class, 'userRoles']);
Route::get('user/by-pfno', [IctmsAccessController::class, 'userByPfno']);
Route::post('module/users', [IctmsAccessController::class, 'moduleUsers']);
Route::post('access/revoke', [IctmsAccessController::class, 'revokeAccess']);

// ICTMS System Monitoring
Route::get('ictms/service', [IctmsMonitoringController::class, 'service']);
Route::get('ictms/interface', [IctmsMonitoringController::class, 'interface']);

// Authentication routes (public)
require app_path('Domains/Authentication/routes.php');

// Public routes (no authentication required)
Route::prefix('qms')->group(function () {
    require app_path('Domains/Ticket/routes.php');
    require app_path('Domains/Feedback/routes.php');
    require app_path('Domains/Mood/routes.php');
    Route::post('devices/authenticate', [DeviceAuthController::class, 'authenticate']);
    Route::get('public/services', [PublicServiceController::class, 'index']);
});

// Device-authenticated routes (X-Device-Token or Authorization: Bearer <device_token>)
Route::prefix('qms')->middleware('device.auth')->group(function () {
    Route::patch('devices/me', [DeviceAuthController::class, 'updateSession']);

    //get wating tickets and current serving tickets per office
    Route::get('tickets/waiting-and-serving', [TicketController::class, 'getWaitingAndServingTicketsPerOffice']);
});



// Protected API routes (Sanctum)
Route::prefix('qms')->middleware('auth:sanctum')->group(function () {
    // Domain routes
    require app_path('Domains/Tenant/routes.php');
    require app_path('Domains/Counter/routes.php');
    require app_path('Domains/CounterType/routes.php');
    require app_path('Domains/Service/routes.php');
    require app_path('Domains/Service/ServiceDocument/routes.php');
    require app_path('Domains/Device/routes.php');
    require app_path('Domains/Audit/routes.php');
    require app_path('Domains/Notification/routes.php');
    require app_path('Domains/Dashboard/route.php');
    require app_path('Domains/Queue/routes.php');
    require app_path('Domains/Bot/routes.php');
    require app_path('Domains/Mood/admin_routes.php');
});
