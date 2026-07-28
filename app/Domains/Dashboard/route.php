<?php

use App\Domains\Dashboard\Controllers\DashboardsController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard/supervisor', [DashboardsController::class, 'supervisorDashboard'])->name('dashboard.supervisor');
Route::get('dashboard/clerk', [DashboardsController::class, 'clerkDashboard'])->name('dashboard.clerk');
Route::get('dashboard/admin', [DashboardsController::class, 'adminDashboard'])->name('dashboard.admin');
Route::get('dashboard/tenant', [DashboardsController::class, 'tenantDashboard'])->name('dashboard.tenant');
Route::get('dashboard/office-activities', [DashboardsController::class, 'officeActivities'])->name('dashboard.office-activities');
Route::get('dashboard/office-activities/tickets', [DashboardsController::class, 'officeActivityTickets'])->name('dashboard.office-activities.tickets');
Route::get('dashboard/office-activities/export', [DashboardsController::class, 'exportOfficeActivityTickets'])->name('dashboard.office-activities.export');
