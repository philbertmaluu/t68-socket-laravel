<?php

use App\Domains\Ticket\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::apiResource('tickets', TicketController::class);
