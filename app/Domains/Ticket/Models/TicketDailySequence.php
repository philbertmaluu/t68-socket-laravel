<?php

namespace App\Domains\Ticket\Models;

use Illuminate\Database\Eloquent\Model;

class TicketDailySequence extends Model
{
    protected $table = 'ticket_daily_sequences';

    protected $fillable = [
        'tenant_id',
        'office_id',
        'issued_on',
        'last_value',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'last_value' => 'integer',
            'issued_on' => 'date',
        ];
    }
}
