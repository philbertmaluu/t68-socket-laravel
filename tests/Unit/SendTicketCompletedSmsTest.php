<?php

namespace Tests\Unit;

use App\Events\TicketCompleted;
use App\Domains\Ticket\Models\Ticket;
use App\Listeners\SendTicketCompletedSms;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SendTicketCompletedSmsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_listener_sends_sms_only_once_per_ticket(): void
    {
        Cache::flush();

        $ticket = new Ticket([
            'phone_number' => '0712345678',
            'ticket_number' => 'A001',
        ]);
        $ticket->id = 42;

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('sendTicketCompletedNotification')
            ->once()
            ->with($ticket)
            ->andReturn(['success' => true, 'message' => 'ok', 'data' => null]);

        $listener = new SendTicketCompletedSms($notificationService);
        $event = new TicketCompleted($ticket);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertTrue(Cache::has('ticket_completed_sms_42'));
    }
}
