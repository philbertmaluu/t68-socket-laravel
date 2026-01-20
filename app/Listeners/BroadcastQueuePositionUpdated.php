<?php

namespace App\Listeners;

use App\Events\QueuePositionUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastQueuePositionUpdated
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
    public function handle(QueuePositionUpdated $event): void
    {
        // The event will automatically broadcast since it implements ShouldBroadcast
        // This listener can be used for additional logic like logging, notifications, etc.
        
        Log::info('Queue position updated', [
            'ticket_id' => $event->ticket->id,
            'ticket_number' => $event->ticket->ticket_number,
            'queue_id' => $event->ticket->queue_id,
            'queue_position' => $event->ticket->queue_position,
            'office_id' => $event->ticket->office_id,
        ]);
    }
}
