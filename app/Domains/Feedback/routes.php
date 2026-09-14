<?php

use App\Domains\Feedback\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

// Public feedback submission from QR token.
Route::post('feedback', [FeedbackController::class, 'submit']);
Route::get('feedback/context', [FeedbackController::class, 'context']);

// Portal client historically called `/api/qms/public/qms/feedback`.
Route::get('public/qms/feedback/{token}', [FeedbackController::class, 'show'])
    ->where('token', '[^/]+');
Route::post('public/qms/feedback', [FeedbackController::class, 'submit']);

// Device-authenticated QR generation endpoints.
Route::middleware('device.auth')->group(function () {
    Route::get('feedback/qr/general', [FeedbackController::class, 'generateGeneralQr']);
    Route::get('feedback/qr/ticket', [FeedbackController::class, 'generateTicketQr']);
});
