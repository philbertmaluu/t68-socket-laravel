<?php

namespace Tests\Unit;

use App\Domains\Ticket\Models\Ticket;
use App\Events\TicketTransferAccepted;
use App\Listeners\SendTicketTransferAcceptedSms;
use App\Services\NotificationService;
use Mockery;
use Tests\TestCase;

class SendTicketTransferAcceptedSmsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_listener_sends_transfer_sms_when_phone_exists(): void
    {
        $ticket = new Ticket([
            'phone_number' => '0748304649',
            'ticket_number' => 'A1',
        ]);
        $ticket->id = 9;

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('sendTicketTransferAcceptedNotification')
            ->once()
            ->with($ticket)
            ->andReturn(['success' => true, 'message' => 'ok', 'data' => null]);

        $listener = new SendTicketTransferAcceptedSms($notificationService);
        $listener->handle(new TicketTransferAccepted($ticket));

        $this->addToAssertionCount(1);
    }

    public function test_listener_skips_when_phone_missing(): void
    {
        $ticket = new Ticket([
            'phone_number' => null,
            'ticket_number' => 'A1',
        ]);
        $ticket->id = 9;

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldNotReceive('sendTicketTransferAcceptedNotification');

        $listener = new SendTicketTransferAcceptedSms($notificationService);
        $listener->handle(new TicketTransferAccepted($ticket));

        $this->addToAssertionCount(1);
    }
}
