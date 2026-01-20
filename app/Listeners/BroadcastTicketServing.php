<?php

namespace App\Listeners;

use App\Events\TicketServing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastTicketServing
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
    public function handle(TicketServing $event): void
    {
        // The event will automatically broadcast since it implements ShouldBroadcast
        // This listener can be used for additional logic like logging, notifications, etc.
        
        Log::info('Ticket serving', [
            'ticket_id' => $event->ticket->id,
            'ticket_number' => $event->ticket->ticket_number,
            'queue_id' => $event->ticket->queue_id,
            'counter_id' => $event->ticket->counter_id,
            'clerk_id' => $event->ticket->clerk_id,
            'office_id' => $event->ticket->office_id,
        ]);
    }
}
