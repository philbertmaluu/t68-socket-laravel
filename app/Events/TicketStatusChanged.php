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

class TicketStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Ticket $ticket,
        public string $oldStatus,
        public string $newStatus
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
        return 'ticket.status.changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'ticket_number' => $this->ticket->ticket_number,
                'service_type' => $this->ticket->service_type,
                'queue_id' => $this->ticket->queue_id,
                'member_number' => $this->ticket->member_number,
                'member_name' => $this->ticket->member_name,
                'status' => $this->ticket->status,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'counter_id' => $this->ticket->counter_id,
                'clerk_id' => $this->ticket->clerk_id,
                'office_id' => $this->ticket->office_id,
            ],
        ];
    }
}
