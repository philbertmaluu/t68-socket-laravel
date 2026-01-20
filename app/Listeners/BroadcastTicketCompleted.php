<?php

namespace App\Listeners;

use App\Events\TicketCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastTicketCompleted
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TicketCompleted $event): void
    {
        // The event will automatically broadcast since it implements ShouldBroadcast
        // This listener can be used for additional logic like logging, notifications, etc.
        
        Log::info('Ticket completed', [
            'ticket_id' => $event->ticket->id,
            'ticket_number' => $event->ticket->ticket_number,
            'queue_id' => $event->ticket->queue_id,
            'duration_seconds' => $event->ticket->duration_seconds,
            'office_id' => $event->ticket->office_id,
        ]);
    }
}
