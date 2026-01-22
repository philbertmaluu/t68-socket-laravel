<?php

use App\Http\Controllers\LogViewController;
use App\Domains\Ticket\Models\Ticket;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Log viewer routes
Route::prefix('logs')->name('logs.')->group(function () {
    Route::get('/', [LogViewController::class, 'index'])->name('view');
    Route::post('/clear', [LogViewController::class, 'clear'])->name('clear');
    Route::get('/download', [LogViewController::class, 'download'])->name('download');
});

// Test routes for ticket queue system
Route::prefix('test/tickets')->group(function () {
    // Create a test ticket
    Route::post('/create', function (Request $request) {
        $ticket = Ticket::create([
            'ticket_number' => 'TEST-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'service_type' => $request->input('service_type', 'Test Service'),
            'queue_id' => $request->input('queue_id', 'test-queue-1'),
            'member_name' => $request->input('member_name', 'Test Customer'),
            'member_number' => $request->input('member_number', 'M' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT)),
            'phone_number' => $request->input('phone_number', '+1234567890'),
            'estimated_time' => $request->input('estimated_time', 300),
            'priority' => $request->boolean('priority', false),
            'status' => 'waiting',
            'office_id' => $request->input('office_id', 'office-1'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'queue_position' => $ticket->queue_position,
                'estimated_wait_time' => $ticket->getEstimatedWaitTime(),
            ],
        ]);
    });

    // Get queue status
    Route::get('/queue/{queueId}', function ($queueId) {
        $tickets = Ticket::where('queue_id', $queueId)
            ->whereIn('status', ['waiting', 'called'])
            ->orderBy('queue_position')
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status,
                    'queue_position' => $ticket->queue_position,
                    'estimated_wait_time' => $ticket->getEstimatedWaitTime(),
                    'priority' => $ticket->priority,
                    'created_at' => $ticket->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'queue_id' => $queueId,
            'tickets' => $tickets,
            'count' => $tickets->count(),
        ]);
    });

    // Update ticket status
    Route::patch('/{ticket}/status', function (Ticket $ticket, Request $request) {
        $status = $request->input('status');
        $validStatuses = ['waiting', 'called', 'serving', 'completed', 'skipped', 'cancelled'];

        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Valid statuses: ' . implode(', ', $validStatuses),
            ], 400);
        }

        $updateData = ['status' => $status];

        // Set timestamps based on status
        switch ($status) {
            case 'called':
                $updateData['called_at'] = now();
                break;
            case 'serving':
                $updateData['serving_started_at'] = now();
                break;
            case 'completed':
                $updateData['completed_at'] = now();
                if ($ticket->serving_started_at) {
                    $updateData['duration_seconds'] = now()->diffInSeconds($ticket->serving_started_at);
                }
                break;
        }

        $ticket->update($updateData);
        $ticket->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated',
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'queue_position' => $ticket->queue_position,
                'estimated_wait_time' => $ticket->getEstimatedWaitTime(),
            ],
        ]);
    });

    // Get ticket details
    Route::get('/{ticket}', function (Ticket $ticket) {
        return response()->json([
            'success' => true,
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'service_type' => $ticket->service_type,
                'queue_id' => $ticket->queue_id,
                'status' => $ticket->status,
                'queue_position' => $ticket->queue_position,
                'estimated_wait_time' => $ticket->getEstimatedWaitTime(),
                'priority' => $ticket->priority,
                'member_name' => $ticket->member_name,
                'is_next_in_queue' => $ticket->isNextInQueue(),
                'created_at' => $ticket->created_at->toIso8601String(),
            ],
        ]);
    });
});
