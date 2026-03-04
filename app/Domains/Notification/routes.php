<?php

use App\Domains\Notification\Controllers\NotificationTemplateController;
use Illuminate\Support\Facades\Route;

// Notification templates management (protected by Sanctum in routes/api.php).
// You can further restrict these routes via policies/permissions (e.g., Content manager role).
Route::apiResource('notification-templates', NotificationTemplateController::class);

