<?php

namespace App\Services;

use App\Events\QueuePositionUpdated;
use App\Domains\Ticket\Models\Ticket;
use Illuminate\Support\Facades\DB;

class QueueService
{
    /**
     * Add ticket to queue and calculate its position.
     *
     * @param Ticket $ticket
     * @return int The queue position assigned
     */
    public function addToQueue(Ticket $ticket): int
    {
        $position = $this->calculateQueuePosition($ticket);
        
        // Use withoutEvents to prevent triggering updated event (infinite loop)
        $ticket->withoutEvents(function () use ($ticket, $position) {
            $ticket->update(['queue_position' => $position]);
        });

        // Recalculate positions for other tickets if this is a priority ticket
        if ($ticket->priority && $position === 0) {
            $this->recalculateQueuePositions($ticket->queue_id, $ticket->id);
        }

        // Broadcast queue position update
        event(new QueuePositionUpdated($ticket));

        return $position;
    }

    /**
     * Get current   position for a ticket.
     *
     * @param Ticket $ticket
     * @return int|null
     */
    public function getQueuePosition(Ticket $ticket): ?int
    {
        return $ticket->queue_position;
    }

    /**
     * Calculate estimated wait time for a ticket in seconds.
     *
     * @param Ticket $ticket
     * @return int Estimated wait time in seconds
     */
    public function calculateEstimatedWaitTime(Ticket $ticket): int
    {
        if (!$ticket->queue_position || $ticket->queue_position <= 0) {
            return 0;
        }

        // Get all tickets ahead in the queue
        $ticketsAhead = Ticket::where('queue_id', $ticket->queue_id)
            ->whereIn('status', ['waiting', 'called'])
            ->where('id', '!=', $ticket->id)
            ->where(function ($query) use ($ticket) {
                // Tickets with lower position number (higher priority)
                $query->where('queue_position', '<', $ticket->queue_position)
                    ->orWhere(function ($q) use ($ticket) {
                        // Or same position but created earlier
                        $q->where('queue_position', $ticket->queue_position)
                            ->where('created_at', '<', $ticket->created_at);
                    });
            })
            ->orderBy('queue_position')
            ->orderBy('created_at')
            ->get();

        // Sum up estimated times
        $waitTime = 0;
        foreach ($ticketsAhead as $aheadTicket) {
            $waitTime += $aheadTicket->estimated_time ?? 300; // Default 5 minutes if not set
        }

        return $waitTime;
    }

    /**
     * Recalculate queue positions for all tickets in a queue.
     *
     * @param string $queueId
     * @param string|null $excludeTicketId Ticket to exclude from recalculation
     * @return void
     */
    public function recalculateQueuePositions(string $queueId, ?string $excludeTicketId = null): void
    {
        // Get all active tickets in the queue (waiting or called status)
        $tickets = Ticket::where('queue_id', $queueId)
            ->whereIn('status', ['waiting', 'called'])
            ->when($excludeTicketId, function ($query) use ($excludeTicketId) {
                $query->where('id', '!=', $excludeTicketId);
            })
            ->orderBy('priority', 'desc') // Priority tickets first
            ->orderBy('created_at', 'asc') // Then by creation time
            ->get();

        $position = 1;
        $updatedTickets = [];

        foreach ($tickets as $ticket) {
            // Priority tickets get position 0
            if ($ticket->priority) {
                $newPosition = 0;
            } else {
                $newPosition = $position;
                $position++;
            }

            // Only update if position changed
            // Use withoutEvents to prevent infinite loop from triggering updated event
            if ($ticket->queue_position !== $newPosition) {
                $ticket->withoutEvents(function () use ($ticket, $newPosition) {
                    $ticket->update(['queue_position' => $newPosition]);
                });
                $updatedTickets[] = $ticket;
            }
        }

        // Broadcast updates for all tickets that changed position
        foreach ($updatedTickets as $updatedTicket) {
            event(new QueuePositionUpdated($updatedTicket));
        }
    }

    /**
     * Get the next ticket to be called in a queue.
     *
     * @param string $queueId
     * @return Ticket|null
     */
    public function getNextTicket(string $queueId): ?Ticket
    {
        return Ticket::where('queue_id', $queueId)
            ->whereIn('status', ['waiting', 'called'])
            ->orderBy('queue_position', 'asc')
            ->orderBy('created_at', 'asc')
            ->first();
    }

    /**
     * Remove ticket from queue (when completed/cancelled).
     *
     * @param Ticket $ticket
     * @return void
     */
    public function removeFromQueue(Ticket $ticket): void
    {
        $queueId = $ticket->queue_id;
        
        // Use withoutEvents to prevent triggering updated event
        $ticket->withoutEvents(function () use ($ticket) {
            $ticket->update(['queue_position' => null]);
        });

        // Recalculate positions for remaining tickets
        $this->recalculateQueuePositions($queueId);
    }

    /**
     * Calculate queue position for a new ticket.
     *
     * @param Ticket $ticket
     * @return int
     */
    protected function calculateQueuePosition(Ticket $ticket): int
    {
        // Priority tickets always get position 0
        if ($ticket->priority) {
            return 0;
        }

        // Count tickets ahead in the queue (waiting or called status)
        $ticketsAhead = Ticket::where('queue_id', $ticket->queue_id)
            ->whereIn('status', ['waiting', 'called'])
            ->where('id', '!=', $ticket->id)
            ->where(function ($query) {
                // Only count non-priority tickets for position calculation
                $query->where('priority', false)
                    ->orWhereNull('priority');
            })
            ->count();

        // Position is count + 1 (next available position)
        return $ticketsAhead + 1;
    }
}
