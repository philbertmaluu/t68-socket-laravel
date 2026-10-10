<?php

namespace App\Events;

use App\Domains\Ticket\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketTransferAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }
}
