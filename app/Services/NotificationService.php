<?php

namespace App\Services;

use App\Domains\Ticket\Models\Ticket;
use App\Domains\Notification\Services\NotificationTemplateService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationService
{
    public function __construct(
        private NotificationTemplateService $notificationTemplateService
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

            $endpoint = config('services.ictms.endpoint');
            $system = config('services.ictms.system', 'ICTMS');

            if (empty($endpoint)) {
                throw new Exception('ICTMS API endpoint is not configured');
            }

            $payload = [
                'notification_type' => 'sms',
                'notification_method' => 'instant',
                'notification_system' => $system,
                'notification_process' => $process,
                'notification_recipient' => $recipient,
                'notification_body' => $message,
                'notification_expiry' => $expiryHours,
                'notification_attachment' => $attachment,
            ];

            Log::info('Sending SMS notification', [
                'recipient' => $recipient,
                'process' => $process,
                'endpoint' => $endpoint,
            ]);

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                Log::info('SMS notification sent successfully', [
                    'recipient' => $recipient,
                    'process' => $process,
                    'response' => $responseData,
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS notification sent successfully',
                    'data' => $responseData,
                ];
            }

            $responseBody = $response->body();
            $responseJson = $response->json();
            $errorMessage = $responseJson['message'] ?? $responseBody ?? 'Unknown error';
            $statusCode = $response->status();

            Log::error('Failed to send SMS notification', [
                'recipient' => $recipient,
                'process' => $process,
                'status_code' => $statusCode,
                'error' => $errorMessage,
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => "Failed to send SMS: {$errorMessage}",
                'data' => [
                    'status_code' => $statusCode,
                    'error' => $errorMessage,
                ],
            ];
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
            'sms'
        );

        if (!$template) {
            Log::warning('Skipping ticket created SMS: No active ticket_created_sms template found', [
                'ticket_number' => $ticketNumber,
                'locale' => $locale,
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

        $memberName = $ticket->member_name ?: 'Mwanachama';
        $ticketNumber = $ticket->ticket_number;
        $serviceType = $ticket->service_type;
        $duration = $ticket->duration_seconds 
            ? round($ticket->duration_seconds / 60) . ' dakika' 
            : '';

        $message = "Ndugu {$memberName},\n";
        $message .= "Huduma yako kwa tiketi namba {$ticketNumber} ({$serviceType}) imekamilika.\n";
        if ($duration) {
            $message .= "Muda uliotumika: {$duration}.\n";
        }
        $message .= "Asante kwa kutumia huduma za NSSF.\n";
        $message .= "Karibu tena!";

        return $this->sendSms(
            recipient: $ticket->phone_number,
            message: $message,
            process: 'TICKET COMPLETED',
            expiryHours: 4
        );
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
