<?php

namespace Tests\Unit;

use App\Domains\Feedback\Services\FeedbackService;
use App\Domains\Feedback\Services\FeedbackTokenService;
use App\Domains\Ticket\Models\Ticket;
use Tests\TestCase;

class FeedbackSubmitFromTokenTest extends TestCase
{
    public function test_completion_sms_link_token_is_valid_for_feedback_context(): void
    {
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('services.qms.feedback_web_url', 'https://portal-pre.nssf.go.tz/#/qms/feedback');

        $ticket = new Ticket([
            'tenant_id' => '1',
            'ticket_number' => 'T099',
            'clerk_id' => '5',
            'office_id' => 'OFF1',
        ]);
        $ticket->id = 99;

        $feedbackService = new FeedbackService();
        $result = $feedbackService->generateTicketFeedbackUrlForTicket($ticket);
        $context = $feedbackService->getContextFromToken($result['token']);

        $this->assertSame('ticket', $context['feedback_type']);
        $this->assertSame('99', $context['ticket_id']);
        $this->assertSame('T099', $context['ticket_number']);
        $this->assertStringContainsString('portal-pre.nssf.go.tz/#/qms/feedback?token=', $result['url']);
    }
}
