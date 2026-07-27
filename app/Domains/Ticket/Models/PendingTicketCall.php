<?php

namespace App\Domains\Ticket\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PendingTicketCall extends Model
{
    use HasUuids;

    protected $table = 'pending_ticket_calls';

    public $timestamps = false;

    public const STATUS_WAITING = 'waiting';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'office_id',
        'clerk_id',
        'status',
        'ticket_id',
        'requested_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];
}
