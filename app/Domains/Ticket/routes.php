<?php

use App\Domains\Ticket\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('tickets/call-next', [TicketController::class, 'callNextTicket'])->middleware('auth:sanctum');
Route::get('tickets/active', [TicketController::class, 'activeTicket'])->middleware('auth:sanctum');
Route::get('tickets/attention', [TicketController::class, 'attention'])->middleware('auth:sanctum');
Route::get('tickets/clerk-history', [TicketController::class, 'getClerksTickets'])->middleware('auth:sanctum');

// Must be registered before tickets/{id} (apiResource), otherwise "waiting-and-serving"
// is treated as a ticket id and Oracle throws ORA-01722.
Route::get('tickets/waiting-and-serving', [TicketController::class, 'getWaitingAndServingTicketsPerOffice'])
    ->middleware('device.auth');

Route::post('tickets/{id}/hold', [TicketController::class, 'hold'])->middleware('auth:sanctum');
Route::post('tickets/{id}/resume-pause', [TicketController::class, 'resumePause'])->middleware('auth:sanctum');
Route::post('tickets/{id}/resume', [TicketController::class, 'resume'])->middleware('auth:sanctum');
Route::post('tickets/{id}/accept-transfer', [TicketController::class, 'acceptTransfer'])->middleware('auth:sanctum');
Route::post('tickets/{id}/no-show', [TicketController::class, 'noShow'])->middleware('auth:sanctum');
Route::post('tickets/{id}/suspend', [TicketController::class, 'suspend'])->middleware('auth:sanctum');

Route::apiResource('tickets', TicketController::class)->whereNumber('ticket');

