<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendTicketCreatedSms
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private NotificationService $notificationService
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TicketCreated $event): void
    {
        $ticket = $event->ticket;

        // Idempotency: prevent sending duplicate SMS for the same ticket
        $cacheKey = 'ticket_created_sms_' . $ticket->id;
        if (Cache::has($cacheKey)) {
            Log::info('Skipping duplicate ticket created SMS (cache hit)', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'phone_number' => $ticket->phone_number,
            ]);
            return;
        }
        // Reserve this ticket for SMS sending for a short window (e.g. 10 minutes)
        Cache::put($cacheKey, true, now()->addMinutes(10));

        // Only send SMS if phone number exists
        if (empty($ticket->phone_number)) {
            Log::debug('Skipping SMS notification for ticket created: No phone number', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return;
        }

        // Check if SMS notifications are enabled
        if (!config('services.ictms.enabled', true)) {
            Log::debug('SMS notifications are disabled', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return;
        }

        try {
            $result = $this->notificationService->sendTicketCreatedNotification($ticket);

            if ($result['success']) {
                Log::info('Ticket created SMS notification sent successfully', [
                    'ticket_number' => $ticket->ticket_number,
                    'phone_number' => $ticket->phone_number,
                ]);
            } else {
                Log::warning('Failed to send ticket created SMS notification', [
                    'ticket_number' => $ticket->ticket_number,
                    'phone_number' => $ticket->phone_number,
                    'error' => $result['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in SendTicketCreatedSms listener', [
                'ticket_number' => $ticket->ticket_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
