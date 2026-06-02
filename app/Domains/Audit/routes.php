<?php

use App\Domains\Audit\Controllers\AuditTrailController;
use Illuminate\Support\Facades\Route;

Route::apiResource('audit-trails', AuditTrailController::class)->only(['index', 'show']);

Route::get('audit-trails/export', [AuditTrailController::class, 'export']);
