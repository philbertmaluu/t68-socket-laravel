<?php

use App\Domains\Bot\Controllers\BotController;
use Illuminate\Support\Facades\Route;

Route::post('bot/chat', [BotController::class, 'chat']);
Route::get('bot/tools', [BotController::class, 'tools']);
