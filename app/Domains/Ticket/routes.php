<?php

use App\Domains\Ticket\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('tickets/call-next', [TicketController::class, 'callNextTicket'])->middleware('auth:sanctum');
Route::get('tickets/clerk-history', [TicketController::class, 'getClerksTickets'])->middleware('auth:sanctum');
Route::apiResource('tickets', TicketController::class);
