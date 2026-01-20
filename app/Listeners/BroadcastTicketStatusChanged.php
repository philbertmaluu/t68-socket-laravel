<?php

namespace App\Listeners;

use App\Events\TicketStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastTicketStatusChanged
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
    public function handle(TicketStatusChanged $event): void
    {
        // The event will automatically broadcast since it implements ShouldBroadcast
        // This listener can be used for additional logic like logging, notifications, etc.
        
        Log::info('Ticket status changed', [
            'ticket_id' => $event->ticket->id,
            'ticket_number' => $event->ticket->ticket_number,
            'old_status' => $event->oldStatus,
            'new_status' => $event->newStatus,
            'queue_id' => $event->ticket->queue_id,
            'office_id' => $event->ticket->office_id,
        ]);
    }
}
