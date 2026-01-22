<?php

namespace App\Events;

use App\Domains\Ticket\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueuePositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Ticket $ticket
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tickets'),
            new Channel("office.{$this->ticket->office_id}"),
            new Channel("queue.{$this->ticket->queue_id}"),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'queue.position.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Calculate estimated wait time
        $queueService = app(\App\Services\QueueService::class);
        $estimatedWaitTime = $queueService->calculateEstimatedWaitTime($this->ticket);

        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'ticket_number' => $this->ticket->ticket_number,
                'queue_id' => $this->ticket->queue_id,
                'queue_position' => $this->ticket->queue_position,
                'estimated_wait_time' => $estimatedWaitTime,
                'status' => $this->ticket->status,
                'priority' => $this->ticket->priority,
                'office_id' => $this->ticket->office_id,
            ],
        ];
    }
}
