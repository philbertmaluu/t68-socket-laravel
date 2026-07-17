<?php

namespace Tests\Unit;

use App\Domains\Feedback\Services\FeedbackService;
use App\Domains\Notification\Models\NotificationTemplate;
use App\Domains\Notification\Services\NotificationTemplateService;
use App\Domains\Ticket\Models\Ticket;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TicketCompletedNotificationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_completed_notification_includes_feedback_url_in_sms_body(): void
    {
        Http::fake([
            'https://ictmspre-api.nssf.go.tz/api/send-notification' => Http::response(['success' => true], 200),
        ]);

        config()->set('services.ictms.endpoint', 'https://ictmspre-api.nssf.go.tz/api/send-notification');

        $ticket = new Ticket([
            'phone_number' => '0748304649',
            'member_name' => 'Philbert David',
            'ticket_number' => 'A1',
            'service_type' => 'Pension',
            'locale' => 'en',
            'tenant_id' => '1',
            'id' => 10,
            'office_id' => 'OFF1',
        ]);

        $template = new NotificationTemplate([
            'body' => "Dear {memberName}, ticket {ticketNumber} done. Rate here: {feedbackUrl}",
        ]);

        $templateService = Mockery::mock(NotificationTemplateService::class);
        $templateService->shouldReceive('findActiveByKeyAndLocale')
            ->once()
            ->with('ticket_completed_sms', 'en', 'sms', '1')
            ->andReturn($template);

        $feedbackService = Mockery::mock(FeedbackService::class);
        $feedbackService->shouldReceive('generateTicketFeedbackUrlForTicket')
            ->once()
            ->with($ticket)
            ->andReturn([
                'url' => 'https://portal-pre.nssf.go.tz/#/qms/feedback?token=abc123',
            ]);

        $service = new NotificationService($templateService, $feedbackService);
        $result = $service->sendTicketCompletedNotification($ticket);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            $body = $request->data()['notification_body'] ?? '';

            return str_contains($body, 'https://portal-pre.nssf.go.tz/#/qms/feedback?token=abc123');
        });
    }
}
