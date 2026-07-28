<?php

use App\Domains\Dashboard\Controllers\DashboardsController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard/supervisor', [DashboardsController::class, 'supervisorDashboard'])->name('dashboard.supervisor');
Route::get('dashboard/clerk', [DashboardsController::class, 'clerkDashboard'])->name('dashboard.clerk');
Route::get('dashboard/admin', [DashboardsController::class, 'adminDashboard'])->name('dashboard.admin');
Route::get('dashboard/tenant', [DashboardsController::class, 'tenantDashboard'])->name('dashboard.tenant');
Route::get('dashboard/office-activities', [DashboardsController::class, 'officeActivities'])->name('dashboard.office-activities');
