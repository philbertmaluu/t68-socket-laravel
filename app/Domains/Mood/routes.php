<?php

use App\Domains\Mood\Controllers\MoodAuthController;
use App\Domains\Mood\Controllers\MoodDeviceController;
use App\Domains\Mood\Controllers\MoodFeedbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('mood')->group(function () {
    Route::post('login', [MoodAuthController::class, 'login']);
    Route::post('refresh-token', [MoodAuthController::class, 'refreshToken']);

    Route::middleware('mood.device.auth')->group(function () {
        Route::post('logout', [MoodAuthController::class, 'logout']);
        Route::post('heartbeat', [MoodAuthController::class, 'heartbeat']);

        Route::get('device', [MoodDeviceController::class, 'device']);
        Route::get('configuration', [MoodDeviceController::class, 'configuration']);
        Route::get('theme', [MoodDeviceController::class, 'theme']);
        Route::get('languages', [MoodDeviceController::class, 'languages']);
        Route::get('messages', [MoodDeviceController::class, 'messages']);
        Route::get('company', [MoodDeviceController::class, 'company']);
        Route::get('rating-options', [MoodDeviceController::class, 'ratingOptions']);
        Route::get('feedback-reasons', [MoodDeviceController::class, 'feedbackReasons']);

        Route::post('general-feedback', [MoodFeedbackController::class, 'submitGeneral']);
        Route::get('general/reasons', [MoodFeedbackController::class, 'generalReasons']);

        Route::get('pending-session', [MoodFeedbackController::class, 'pendingSession']);
        Route::post('counter-feedback', [MoodFeedbackController::class, 'submitCounter']);
        Route::post('session/expire', [MoodFeedbackController::class, 'expireSession']);
        Route::post('session/complete', [MoodFeedbackController::class, 'completeSession']);

        Route::post('offline-sync', [MoodFeedbackController::class, 'offlineSync']);
    });
});
