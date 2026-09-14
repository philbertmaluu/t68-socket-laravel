<?php

use App\Domains\SelfService\Controllers\SelfServiceAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('self-services')->middleware('device.auth')->group(function () {
    Route::post('members/verify', [SelfServiceAuthController::class, 'verifyMember']);
    Route::get('members/details', [SelfServiceAuthController::class, 'memberDetails']);
    Route::get('members/statement', [SelfServiceAuthController::class, 'contributionStatement']);
    Route::post('otp/send', [SelfServiceAuthController::class, 'sendOtp']);
    Route::post('otp/verify', [SelfServiceAuthController::class, 'verifyOtp']);
});
