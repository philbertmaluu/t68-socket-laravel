<?php

namespace Tests\Unit;

use App\Domains\Ticket\Models\Ticket;
use App\Events\TicketCalled;
use App\Listeners\SendTicketCalledSms;
use App\Services\NotificationService;
use Mockery;
use Tests\TestCase;

class SendTicketCalledSmsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_listener_sends_called_sms_when_phone_exists(): void
    {
        $ticket = new Ticket([
            'phone_number' => '0748304649',
            'ticket_number' => 'A1',
            'service_type' => 'Claim Identification',
        ]);
        $ticket->id = 9;

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('sendTicketCalledNotification')
            ->once()
            ->with($ticket)
            ->andReturn(['success' => true, 'message' => 'ok', 'data' => null]);

        $listener = new SendTicketCalledSms($notificationService);
        $listener->handle(new TicketCalled($ticket));

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
        $notificationService->shouldNotReceive('sendTicketCalledNotification');

        $listener = new SendTicketCalledSms($notificationService);
        $listener->handle(new TicketCalled($ticket));

        $this->addToAssertionCount(1);
    }
}
