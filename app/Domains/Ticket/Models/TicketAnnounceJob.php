<?php

namespace App\Domains\Ticket\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TicketAnnounceJob extends Model
{
    use HasUuids;

    protected $table = 'ticket_announce_jobs';

    public $timestamps = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PLAYING = 'playing';
    public const STATUS_DONE = 'done';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'office_id',
        'ticket_id',
        'ticket_number',
        'counter_name',
        'counter_type_name',
        'counter_type_code',
        'status',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** Minimal payload for TV playback (keep wire small). */
    public function toAnnouncePayload(): array
    {
        return [
            'announce_id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'counter_name' => $this->counter_name,
            'counter_type_name' => $this->counter_type_name,
            'counter_type_code' => $this->counter_type_code,
        ];
    }
}
