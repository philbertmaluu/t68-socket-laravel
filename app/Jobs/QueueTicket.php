<?php

namespace App\Jobs;

use App\Domains\Ticket\Models\Ticket;
use App\Services\QueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QueueTicket implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ticket $ticket
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(QueueService $queueService): void
    {
        try {
            // Refresh ticket to ensure we have latest data
            $this->ticket->refresh();

            // Only queue tickets that are in waiting status
            if ($this->ticket->status !== 'waiting') {
                Log::warning('Ticket is not in waiting status, skipping queue', [
                    'ticket_id' => $this->ticket->id,
                    'status' => $this->ticket->status,
                ]);
                return;
            }

            // Add ticket to queue
            $position = $queueService->addToQueue($this->ticket);

            Log::info('Ticket queued successfully', [
                'ticket_id' => $this->ticket->id,
                'ticket_number' => $this->ticket->ticket_number,
                'queue_id' => $this->ticket->queue_id,
                'position' => $position,
                'priority' => $this->ticket->priority,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue ticket', [
                'ticket_id' => $this->ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }
}
