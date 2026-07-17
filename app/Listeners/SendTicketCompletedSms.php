<?php

namespace App\Listeners;

use App\Events\TicketCompleted;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendTicketCompletedSms
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
    public function handle(TicketCompleted $event): void
    {
        $ticket = $event->ticket;

        $cacheKey = 'ticket_completed_sms_' . $ticket->id;
        if (!Cache::add($cacheKey, true, now()->addDays(7))) {
            Log::info('Skipping duplicate ticket completed SMS (cache hit)', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'phone_number' => $ticket->phone_number,
            ]);
            return;
        }

        // Only send SMS if phone number exists
        if (empty($ticket->phone_number)) {
            Cache::forget($cacheKey);
            Log::debug('Skipping SMS notification for ticket completed: No phone number', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return;
        }

        // Check if SMS notifications are enabled
        if (!config('services.ictms.enabled', true)) {
            Cache::forget($cacheKey);
            Log::debug('SMS notifications are disabled', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return;
        }

        try {
            $result = $this->notificationService->sendTicketCompletedNotification($ticket);

            if ($result['success']) {
                Log::info('Ticket completed SMS notification sent successfully', [
                    'ticket_number' => $ticket->ticket_number,
                    'phone_number' => $ticket->phone_number,
                    'duration_seconds' => $ticket->duration_seconds,
                ]);
            } else {
                Log::warning('Failed to send ticket completed SMS notification', [
                    'ticket_number' => $ticket->ticket_number,
                    'phone_number' => $ticket->phone_number,
                    'error' => $result['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in SendTicketCompletedSms listener', [
                'ticket_number' => $ticket->ticket_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
