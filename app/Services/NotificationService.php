<?php

namespace App\Services;

use App\Domains\Feedback\Services\FeedbackService;
use App\Domains\Ticket\Models\Ticket;
use App\Domains\Notification\Services\NotificationTemplateService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationService
{
    public function __construct(
        private NotificationTemplateService $notificationTemplateService,
        private FeedbackService $feedbackService
    ) {
    }

    /**
     * Send SMS notification via ICTMS API
     *
     * @param string $recipient Phone number (e.g., '0718206671')
     * @param string $message Message body
     * @param string $process Process name (e.g., 'MEMBER SMS')
     * @param int|null $expiryHours Expiry hours (default: 4)
     * @param string|null $attachment Attachment URL (optional)
     * @return array{success: bool, message: string, data: array|null}
     */
    public function sendSms(
        string $recipient,
        string $message,
        string $process = 'MEMBER SMS',
        ?int $expiryHours = 4,
        ?string $attachment = null
    ): array {
        try {
            // Validate phone number
            if (empty($recipient)) {
                throw new Exception('Recipient phone number is required');
            }

            // Clean phone number (remove spaces, dashes, etc.)
            $recipient = preg_replace('/[^0-9+]/', '', $recipient);

            // Validate message
            if (empty($message)) {
                throw new Exception('Message body is required');
            }

            return $this->sendInstantNotification(
                type: 'sms',
                recipient: $recipient,
                message: $message,
                process: $process,
                expiryHours: $expiryHours,
                attachment: $attachment,
                successMessage: 'SMS notification sent successfully',
                failurePrefix: 'Failed to send SMS',
            );
        } catch (Exception $e) {
            Log::error('Exception while sending SMS notification', [
                'recipient' => $recipient ?? 'unknown',
                'process' => $process,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => "Exception: {$e->getMessage()}",
                'data' => null,
            ];
        }
    }

    /**
     * Send email notification via the same ICTMS instant send-notification endpoint.
     *
     * @return array{success: bool, message: string, data: array|null}
     */
    public function sendEmail(
        string $recipient,
        string $message,
        string $process = 'MEMBER EMAIL',
        ?int $expiryHours = 4,
        ?string $attachment = null
    ): array {
        try {
            $recipient = strtolower(trim($recipient));
            if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('A valid email address is required');
            }

            if (empty($message)) {
                throw new Exception('Message body is required');
            }

            return $this->sendInstantNotification(
                type: 'email',
                recipient: $recipient,
                message: $message,
                process: $process,
                expiryHours: $expiryHours,
                attachment: $attachment,
                successMessage: 'Email notification sent successfully',
                failurePrefix: 'Failed to send email',
            );
        } catch (Exception $e) {
            Log::error('Exception while sending email notification', [
                'recipient' => $recipient ?? 'unknown',
                'process' => $process,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => "Exception: {$e->getMessage()}",
                'data' => null,
            ];
        }
    }

    /**
     * @return array{success: bool, message: string, data: array|null}
     */
    private function sendInstantNotification(
        string $type,
        string $recipient,
        string $message,
        string $process,
        ?int $expiryHours,
        ?string $attachment,
        string $successMessage,
        string $failurePrefix,
    ): array {
        $endpoint = config('services.ictms.endpoint');
        $system = config('services.ictms.system', 'ICTMS');

        if (empty($endpoint)) {
            throw new Exception('ICTMS API endpoint is not configured');
        }

        $payload = [
            'notification_type' => $type,
            'notification_method' => 'instant',
            'notification_system' => $system,
            'notification_process' => $process,
            'notification_recipient' => $recipient,
            'notification_body' => $message,
            'notification_expiry' => $expiryHours,
            'notification_attachment' => $attachment,
        ];

        Log::info("Sending {$type} notification", [
            'recipient' => $recipient,
            'process' => $process,
            'endpoint' => $endpoint,
        ]);

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::timeout(90)
            ->retry(3, 1000)
            ->post($endpoint, $payload);

        if ($response->successful()) {
            $responseData = $response->json();

            Log::info("{$type} notification sent successfully", [
                'recipient' => $recipient,
                'process' => $process,
                'response' => $responseData,
            ]);

            return [
                'success' => true,
                'message' => $successMessage,
                'data' => $responseData,
            ];
        }

        $responseJson = $response->json();
        $errorMessage = $responseJson['message'] ?? $response->body() ?? 'Unknown error';
        $statusCode = $response->status();

        Log::error("Failed to send {$type} notification", [
            'recipient' => $recipient,
            'process' => $process,
            'status_code' => $statusCode,
            'error' => $errorMessage,
            'response' => $response->body(),
        ]);

        return [
            'success' => false,
            'message' => "{$failurePrefix}: {$errorMessage}",
            'data' => [
                'status_code' => $statusCode,
                'error' => $errorMessage,
            ],
        ];
    }

    /**
     * Send ticket created notification
     *
     * @param Ticket $ticket
     * @return array{success: bool, message: string, data: array|null}
     */
    public function sendTicketCreatedNotification(Ticket $ticket): array
    {
        if (empty($ticket->phone_number)) {
            Log::info('Skipping SMS notification: No phone number for ticket', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return [
                'success' => false,
                'message' => 'No phone number available',
                'data' => null,
            ];
        }

        $memberName = $ticket->member_name ?: 'Mwanachama';
        $ticketNumber = $ticket->ticket_number;
        $serviceType = $ticket->service_type;

        // Try to load customizable template; locale is passed from desktop and stored on the ticket
        $locale = $ticket->locale ?: null;
        $template = $this->notificationTemplateService->findActiveByKeyAndLocale(
            'ticket_created_sms',
            is_string($locale) ? $locale : null,
            'sms',
            $ticket->tenant_id
        );

        if (!$template) {
            Log::warning('Skipping ticket created SMS: No active ticket_created_sms template found', [
                'ticket_number' => $ticketNumber,
                'locale' => $locale,
                'tenant_id' => $ticket->tenant_id,
            ]);
            return [
                'success' => false,
                'message' => 'No ticket_created_sms template configured',
                'data' => null,
            ];
        }

        $message = $this->renderTemplate(
            $template->body,
            [
                'memberName' => $memberName,
                'ticketNumber' => $ticketNumber,
                'serviceType' => $serviceType,
            ]
        );

        return $this->sendSms(
            recipient: $ticket->phone_number,
            message: $message,
            process: 'TICKET CREATED',
            expiryHours: 4
        );
    }

    /**
     * Send ticket completed notification
     *
     * @param Ticket $ticket
     * @return array{success: bool, message: string, data: array|null}
     */
    public function sendTicketCompletedNotification(Ticket $ticket): array
    {
        if (empty($ticket->phone_number)) {
            Log::info('Skipping SMS notification: No phone number for ticket', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return [
                'success' => false,
                'message' => 'No phone number available',
                'data' => null,
            ];
        }

        $memberName = $ticket->member_name ?: '';
        $ticketNumber = $ticket->ticket_number;
        $serviceType = $ticket->service_type;
        $locale = $ticket->locale ?: null;
        $duration = $ticket->duration_seconds
            ? round($ticket->duration_seconds / 60) . ($locale === 'en' ? ' minutes' : ' dakika')
            : '';

        $feedbackUrl = '';
        try {
            $feedback = $this->feedbackService->generateTicketFeedbackUrlForTicket($ticket);
            $feedbackUrl = (string) ($feedback['url'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('Failed to generate feedback URL for ticket completed SMS', [
                'ticket_number' => $ticketNumber,
                'error' => $e->getMessage(),
            ]);
        }

        $template = $this->notificationTemplateService->findActiveByKeyAndLocale(
            'ticket_completed_sms',
            is_string($locale) ? $locale : null,
            'sms',
            $ticket->tenant_id
        );

        if (!$template) {
            Log::warning('Skipping ticket completed SMS: No active ticket_completed_sms template found', [
                'ticket_number' => $ticketNumber,
                'locale' => $locale,
                'tenant_id' => $ticket->tenant_id,
            ]);
            return [
                'success' => false,
                'message' => 'No ticket_completed_sms template configured',
                'data' => null,
            ];
        }

        $message = $this->renderTemplate(
            $template->body,
            [
                'memberName' => $memberName,
                'ticketNumber' => $ticketNumber,
                'serviceType' => $serviceType,
                'duration' => $duration,
                'feedbackUrl' => $feedbackUrl,
            ]
        );

        return $this->sendSms(
            recipient: $ticket->phone_number,
            message: $message,
            process: 'TICKET COMPLETED',
            expiryHours: 4
        );
    }

    /**
     * SMS when a ticket is called or recalled. Always prefers the Swahili template.
     *
     * @return array{success: bool, message: string, data: array|null}
     */
    public function sendTicketCalledNotification(Ticket $ticket): array
    {
        if (empty($ticket->phone_number)) {
            Log::info('Skipping SMS notification: No phone number for ticket', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return [
                'success' => false,
                'message' => 'No phone number available',
                'data' => null,
            ];
        }

        $placeholders = $this->counterPlaceholders($ticket);

        $template = $this->notificationTemplateService->findActiveByKeyAndLocale(
            'ticket_called_sms',
            'sw',
            'sms',
            $ticket->tenant_id
        );

        $message = $template
            ? $this->renderTemplate($template->body, $placeholders)
            : "Ndugu Mteja tiketi yako imeitwa tafadhali elekea {$placeholders['counterTypeName']} nambari {$placeholders['counterName']} ili kupokea huduma";

        return $this->sendSms(
            recipient: $ticket->phone_number,
            message: $message,
            process: 'TICKET CALLED',
            expiryHours: 4
        );
    }

    /**
     * SMS when a transferred ticket is accepted at the destination counter.
     *
     * @return array{success: bool, message: string, data: array|null}
     */
    public function sendTicketTransferAcceptedNotification(Ticket $ticket): array
    {
        if (empty($ticket->phone_number)) {
            Log::info('Skipping SMS notification: No phone number for ticket', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            return [
                'success' => false,
                'message' => 'No phone number available',
                'data' => null,
            ];
        }

        $placeholders = $this->counterPlaceholders($ticket);

        $template = $this->notificationTemplateService->findActiveByKeyAndLocale(
            'ticket_transfer_accepted_sms',
            'sw',
            'sms',
            $ticket->tenant_id
        );

        $message = $template
            ? $this->renderTemplate($template->body, $placeholders)
            : "Ndugu Mteja tiketi yako imeahamishwa tafadhali elekea {$placeholders['counterTypeName']} nambari {$placeholders['counterName']} ili kupokea huduma";

        return $this->sendSms(
            recipient: $ticket->phone_number,
            message: $message,
            process: 'TICKET TRANSFER ACCEPTED',
            expiryHours: 4
        );
    }

    /**
     * @return array{counterTypeName: string, counterName: string}
     */
    private function counterPlaceholders(Ticket $ticket): array
    {
        $ticket->loadMissing(['counter.counterType']);

        return [
            'counterTypeName' => $ticket->counter?->counterType?->name ?: 'dirisha',
            'counterName' => $ticket->counter?->name ?: ($ticket->counter_id ? (string) $ticket->counter_id : ''),
        ];
    }

    /**
     * Very small helper to replace {placeholders} in template bodies.
     */
    private function renderTemplate(string $body, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }
        return strtr($body, $replacements);
    }
}
