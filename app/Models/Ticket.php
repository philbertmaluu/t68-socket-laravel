<?php

namespace App\Models;

use App\Events\TicketCalled;
use App\Events\TicketCompleted;
use App\Events\TicketCreated;
use App\Events\TicketServing;
use App\Events\TicketStatusChanged;
use App\Jobs\QueueTicket;
use App\Services\QueueService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    /**
     * The old status before update.
     *
     * @var string|null
     */
    protected $oldStatus = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tickets';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket_number',
        'service_type',
        'service_id',
        'queue_id',
        'member_number',
        'member_name',
        'phone_number',
        'estimated_time',
        'priority',
        'status',
        'counter_id',
        'clerk_id',
        'called_at',
        'serving_started_at',
        'completed_at',
        'duration_seconds',
        'transferred_to_counter_id',
        'office_id',
        'queue_position',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'boolean',
            'estimated_time' => 'integer',
            'duration_seconds' => 'integer',
            'queue_position' => 'integer',
            'called_at' => 'datetime',
            'serving_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Fire TicketCreated event and dispatch QueueTicket job when a new ticket is created
        static::created(function (Ticket $ticket) {
            event(new TicketCreated($ticket));
            
            // Dispatch job to queue the ticket asynchronously
            QueueTicket::dispatch($ticket);
        });

        // Track old status before update
        static::updating(function (Ticket $ticket) {
            $ticket->oldStatus = $ticket->getOriginal('status');
        });

        // Fire status-specific events after update
        static::updated(function (Ticket $ticket) {
            $oldStatus = $ticket->oldStatus;
            $newStatus = $ticket->status;

            // Fire general status changed event
            if ($oldStatus !== $newStatus) {
                event(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
            }

            // Handle queue position updates when status changes
            if ($oldStatus !== $newStatus) {
                $queueService = app(QueueService::class);
                
                // Recalculate queue positions when ticket is processed or removed
                if (in_array($newStatus, ['serving', 'completed', 'cancelled', 'skipped'])) {
                    $queueService->removeFromQueue($ticket);
                } elseif ($newStatus === 'waiting' && $oldStatus !== 'waiting') {
                    // If ticket goes back to waiting, re-add to queue
                    $queueService->addToQueue($ticket);
                } else {
                    // Recalculate positions for other tickets
                    $queueService->recalculateQueuePositions($ticket->queue_id, $ticket->id);
                }
            }

            // Fire specific events based on new status
            match ($newStatus) {
                'called' => event(new TicketCalled($ticket)),
                'serving' => event(new TicketServing($ticket)),
                'completed' => event(new TicketCompleted($ticket)),
                default => null,
            };
        });
    }

    /**
     * Get the possible status values.
     *
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            'waiting',
            'called',
            'serving',
            'completed',
            'skipped',
            'transferred',
            'cancelled',
        ];
    }

    /**
     * Check if ticket is in waiting status.
     *
     * @return bool
     */
    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    /**
     * Check if ticket is called.
     *
     * @return bool
     */
    public function isCalled(): bool
    {
        return $this->status === 'called';
    }

    /**
     * Check if ticket is being served.
     *
     * @return bool
     */
    public function isServing(): bool
    {
        return $this->status === 'serving';
    }

    /**
     * Check if ticket is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get the queue position for this ticket.
     *
     * @return int|null
     */
    public function getQueuePosition(): ?int
    {
        return $this->queue_position;
    }

    /**
     * Get the estimated wait time for this ticket in seconds.
     *
     * @return int
     */
    public function getEstimatedWaitTime(): int
    {
        $queueService = app(QueueService::class);
        return $queueService->calculateEstimatedWaitTime($this);
    }

    /**
     * Check if this ticket is next in queue to be called.
     *
     * @return bool
     */
    public function isNextInQueue(): bool
    {
        $queueService = app(QueueService::class);
        $nextTicket = $queueService->getNextTicket($this->queue_id);
        
        return $nextTicket && $nextTicket->id === $this->id;
    }
}