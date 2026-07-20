<?php

namespace App\Listeners;

use App\Domains\Mood\Services\MoodFeedbackSessionService;
use App\Events\TicketCompleted;
use Illuminate\Support\Facades\Log;

class CreateMoodFeedbackSession
{
    public function __construct(
        private MoodFeedbackSessionService $sessionService
    ) {
    }

    public function handle(TicketCompleted $event): void
    {
        try {
            $session = $this->sessionService->createForTicket($event->ticket);

            if ($session) {
                Log::info('Mood feedback session created', [
                    'session_id' => $session->id,
                    'ticket_id' => $event->ticket->id,
                    'device_id' => $session->device_id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create mood feedback session', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
