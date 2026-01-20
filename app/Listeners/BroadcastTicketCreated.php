<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastTicketCreated
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
    public function handle(TicketCreated $event): void
    {
        // The event will automatically broadcast since it implements ShouldBroadcast
        // This listener can be used for additional logic like logging, notifications, etc.
        
        Log::info('Ticket created', [
            'ticket_id' => $event->ticket->id,
            'ticket_number' => $event->ticket->ticket_number,
            'queue_id' => $event->ticket->queue_id,
            'office_id' => $event->ticket->office_id,
        ]);
    }
}
