<?php

namespace App\Listeners;

use App\Domains\Ticket\Models\Ticket;
use App\Events\TicketCalled;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendTicketCalledSms
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    public function handle(TicketCalled $event): void
    {
        $ticket = $event->ticket;

        $send = function () use ($ticket): void {
            if ($ticket->exists) {
                try {
                    $ticket->refresh();
                } catch (\Throwable) {
                    // Keep the in-memory ticket when it was not persisted (unit tests).
                }
            }

            $this->sendForTicket($ticket);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($send);
            return;
        }

        $send();
    }

    private function sendForTicket(Ticket $ticket): void
    {
        if (empty($ticket->phone_number)) {
            Log::debug('Skipping SMS notification for ticket called: No phone number', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return;
        }

        if (!config('services.ictms.enabled', true)) {
            Log::debug('SMS notifications are disabled', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return;
        }

        $dedupeKey = 'ticket_called_sms:' . $ticket->id . ':' . (string) $ticket->counter_id;
        if (!Cache::add($dedupeKey, true, now()->addSeconds(8))) {
            Log::debug('Skipping duplicate ticket called SMS', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return;
        }

        try {
            $result = $this->notificationService->sendTicketCalledNotification($ticket);

            if ($result['success']) {
                Log::info('Ticket called SMS notification sent successfully', [
                    'ticket_number' => $ticket->ticket_number,
                    'phone_number' => $ticket->phone_number,
                ]);
            } else {
                Log::warning('Failed to send ticket called SMS notification', [
                    'ticket_number' => $ticket->ticket_number,
                    'phone_number' => $ticket->phone_number,
                    'error' => $result['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in SendTicketCalledSms listener', [
                'ticket_number' => $ticket->ticket_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
