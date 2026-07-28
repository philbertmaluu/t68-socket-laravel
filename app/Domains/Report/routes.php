<?php

use App\Domains\Report\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('reports/queue-summary', [ReportController::class, 'queueSummary'])->name('reports.queue-summary');
Route::get('reports/daily-trends', [ReportController::class, 'dailyTrends'])->name('reports.daily-trends');
Route::get('reports/service-mix', [ReportController::class, 'serviceMix'])->name('reports.service-mix');
Route::get('reports/hourly-activity', [ReportController::class, 'hourlyActivity'])->name('reports.hourly-activity');
Route::get('reports/counter-performance', [ReportController::class, 'counterPerformance'])->name('reports.counter-performance');
Route::get('reports/clerk-performance', [ReportController::class, 'clerkPerformance'])->name('reports.clerk-performance');
Route::get('reports/tickets', [ReportController::class, 'tickets'])->name('reports.tickets');
