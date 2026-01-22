<?php

namespace App\Domains\Ticket\Models;

use App\Events\TicketCalled;
use App\Events\TicketCompleted;
use App\Events\TicketCreated;
use App\Events\TicketServing;
use App\Events\TicketStatusChanged;
use App\Jobs\QueueTicket;
use App\Shared\Traits\HasTenant;
use App\Services\QueueService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory, HasTenant;

    protected $oldStatus = null;
    protected $table = 'tickets';

    protected $fillable = [
        'ticket_number',
        'tenant_id',
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

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (Ticket $ticket) {
            event(new TicketCreated($ticket));
            QueueTicket::dispatch($ticket);
        });

        static::updating(function (Ticket $ticket) {
            $ticket->oldStatus = $ticket->getOriginal('status');
        });

        static::updated(function (Ticket $ticket) {
            $oldStatus = $ticket->oldStatus;
            $newStatus = $ticket->status;
            
            $changedAttributes = array_keys($ticket->getChanges());
            $onlyQueuePositionChanged = count($changedAttributes) === 1 && 
                                       in_array('queue_position', $changedAttributes);

            if (!$onlyQueuePositionChanged && $oldStatus !== $newStatus) {
                $queueService = app(QueueService::class);
                
                if (in_array($newStatus, ['serving', 'completed', 'cancelled', 'skipped'])) {
                    $queueService->removeFromQueue($ticket);
                } elseif ($newStatus === 'waiting' && $oldStatus !== 'waiting') {
                    $queueService->addToQueue($ticket);
                } else {
                    $queueService->recalculateQueuePositions($ticket->queue_id, $ticket->id);
                }
            }

            if ($oldStatus !== $newStatus) {
                event(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
            }

            if ($oldStatus !== $newStatus) {
                match ($newStatus) {
                    'called' => event(new TicketCalled($ticket)),
                    'serving' => event(new TicketServing($ticket)),
                    'completed' => event(new TicketCompleted($ticket)),
                    default => null,
                };
            }
        });
    }

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

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function isCalled(): bool
    {
        return $this->status === 'called';
    }

    public function isServing(): bool
    {
        return $this->status === 'serving';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getQueuePosition(): ?int
    {
        return $this->queue_position;
    }

    public function getEstimatedWaitTime(): int
    {
        $queueService = app(QueueService::class);
        return $queueService->calculateEstimatedWaitTime($this);
    }

    public function isNextInQueue(): bool
    {
        $queueService = app(QueueService::class);
        $nextTicket = $queueService->getNextTicket($this->queue_id);
        
        return $nextTicket && $nextTicket->id === $this->id;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tenant\Models\Tenant::class, 'tenant_id', 'id');
    }
}
