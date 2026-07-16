<?php

use App\Domains\Ticket\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('tickets/call-next', [TicketController::class, 'callNextTicket'])->middleware('auth:sanctum');
Route::get('tickets/active', [TicketController::class, 'activeTicket'])->middleware('auth:sanctum');
Route::get('tickets/attention', [TicketController::class, 'attention'])->middleware('auth:sanctum');
Route::post('tickets/{id}/hold', [TicketController::class, 'hold'])->middleware('auth:sanctum');
Route::post('tickets/{id}/resume-pause', [TicketController::class, 'resumePause'])->middleware('auth:sanctum');
Route::post('tickets/{id}/resume', [TicketController::class, 'resume'])->middleware('auth:sanctum');
Route::post('tickets/{id}/accept-transfer', [TicketController::class, 'acceptTransfer'])->middleware('auth:sanctum');
Route::get('tickets/clerk-history', [TicketController::class, 'getClerksTickets'])->middleware('auth:sanctum');
Route::apiResource('tickets', TicketController::class);
