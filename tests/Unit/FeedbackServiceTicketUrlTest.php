<?php

namespace Tests\Unit;

use App\Domains\Feedback\Services\FeedbackTokenService;
use App\Domains\Ticket\Models\Ticket;
use App\Domains\Feedback\Services\FeedbackService;
use Tests\TestCase;

class FeedbackServiceTicketUrlTest extends TestCase
{
    public function test_generate_ticket_feedback_url_for_ticket_builds_portal_link(): void
    {
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('services.qms.feedback_web_url', 'https://portal-pre.nssf.go.tz/#/qms/feedback');

        $ticket = new Ticket([
            'tenant_id' => '1',
            'ticket_number' => 'B007',
            'clerk_id' => '9',
            'office_id' => 'OFF2',
        ]);
        $ticket->id = 42;

        $service = new FeedbackService();
        $result = $service->generateTicketFeedbackUrlForTicket($ticket);

        $this->assertSame('ticket', $result['feedback_type']);
        $this->assertSame('42', $result['ticket_id']);
        $this->assertStringStartsWith('https://portal-pre.nssf.go.tz/#/qms/feedback?token=', $result['url']);
        $this->assertNotEmpty($result['token']);

        $tokenService = new FeedbackTokenService();
        $payload = $tokenService->verifyToken($result['token']);
        $this->assertSame('ticket', $payload['type']);
        $this->assertSame('42', $payload['ticket_id']);
        $this->assertSame('B007', $payload['ticket_number']);
    }
}
