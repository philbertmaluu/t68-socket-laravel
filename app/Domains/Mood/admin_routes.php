<?php

use App\Domains\Mood\Controllers\MoodFeedbackAdminController;
use Illuminate\Support\Facades\Route;

Route::get('mood/feedbacks', [MoodFeedbackAdminController::class, 'index']);
