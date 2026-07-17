<?php

namespace App\Domains\Feedback\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Feedback\Models\Feedback;
use App\Domains\Feedback\Repositories\FeedbackRepository;
use App\Domains\Ticket\Models\Ticket;

class FeedbackService
{
    private FeedbackRepository $repository;
    private FeedbackTokenService $tokenService;

    public function __construct()
    {
        $this->repository = new FeedbackRepository();
        $this->tokenService = new FeedbackTokenService();
    }

    public function getContextFromToken(string $token): array
    {
        $payload = $this->tokenService->verifyToken($token);
        return [
            'feedback_type' => (string) ($payload['type'] ?? 'general'),
            'tenant_id' => (string) ($payload['tenant_id'] ?? ''),
            'office_id' => isset($payload['office_id']) ? (string) $payload['office_id'] : null,
            'ticket_id' => isset($payload['ticket_id']) ? (string) $payload['ticket_id'] : null,
            'ticket_number' => isset($payload['ticket_number']) ? (string) $payload['ticket_number'] : null,
            'clerk_id' => isset($payload['clerk_id']) ? (string) $payload['clerk_id'] : null,
            'expires_at' => (int) ($payload['exp'] ?? 0),
        ];
    }

    public function submitFeedback(array $data): Feedback
    {
        $payload = $this->tokenService->verifyToken((string) $data['token']);
        $feedbackType = (string) ($payload['type'] ?? 'general');
        $tenantId = (string) ($payload['tenant_id'] ?? '');
        if ($tenantId === '') {
            throw new \RuntimeException('Feedback token missing tenant');
        }

        $record = [
            'tenant_id' => $tenantId,
            'feedback_type' => $feedbackType,
            'rating' => (int) $data['rating'],
            'comment_key' => $data['comment_key'] ?? null,
            'comment_label' => $data['comment_label'] ?? null,
            'comment_text' => $data['comment_text'] ?? null,
            'office_id' => isset($payload['office_id']) ? (string) $payload['office_id'] : null,
            'source' => $data['source'] ?? 'feedback-page',
            'submitted_at' => now(),
        ];

        if ($feedbackType === Feedback::TYPE_TICKET) {
            $ticketId = (string) ($payload['ticket_id'] ?? '');
            $ticketNumber = (string) ($payload['ticket_number'] ?? '');
            if ($ticketId === '' || $ticketNumber === '') {
                throw new \RuntimeException('Ticket feedback token missing ticket context');
            }

            $ticket = Ticket::withoutTenant()
                ->where('id', $ticketId)
                ->where('ticket_number', $ticketNumber)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$ticket) {
                throw new \RuntimeException('Ticket context from feedback token is invalid');
            }

            $record['ticket_id'] = (int) $ticket->id;
            $record['ticket_number'] = (string) $ticket->ticket_number;
            $record['clerk_id'] = isset($payload['clerk_id']) ? (string) $payload['clerk_id'] : ($ticket->clerk_id ? (string) $ticket->clerk_id : null);
            $record['clerk_rating'] = isset($data['clerk_rating']) ? (int) $data['clerk_rating'] : null;
            $record['office_id'] = (string) ($ticket->office_id ?? $record['office_id']);
        }

        return $this->repository->create($record);
    }

    public function generateGeneralFeedbackUrlForDevice(Device $device): array
    {
        $token = $this->tokenService->createGeneralToken((string) $device->tenant_id, (string) $device->office_id);

        return [
            'token' => $token,
            'url' => $this->buildFeedbackUrl($token),
            'feedback_type' => Feedback::TYPE_GENERAL,
            'office_id' => (string) $device->office_id,
        ];
    }

    public function generateTicketFeedbackUrlForDevice(Device $device, string $ticketId): array
    {
        $ticket = Ticket::withoutTenant()
            ->where('id', $ticketId)
            ->where('tenant_id', $device->tenant_id)
            ->first();

        if (!$ticket) {
            throw new \RuntimeException('Ticket not found for feedback QR');
        }

        return $this->generateTicketFeedbackUrlForTicket($ticket);
    }

    public function generateTicketFeedbackUrlForTicket(Ticket $ticket): array
    {
        $token = $this->tokenService->createTicketToken(
            (string) $ticket->tenant_id,
            (string) $ticket->id,
            (string) $ticket->ticket_number,
            $ticket->clerk_id ? (string) $ticket->clerk_id : null,
            (string) $ticket->office_id,
        );

        return [
            'token' => $token,
            'url' => $this->buildFeedbackUrl($token),
            'feedback_type' => Feedback::TYPE_TICKET,
            'ticket_id' => (string) $ticket->id,
            'ticket_number' => (string) $ticket->ticket_number,
            'clerk_id' => $ticket->clerk_id ? (string) $ticket->clerk_id : null,
            'office_id' => (string) $ticket->office_id,
        ];
    }

    private function buildFeedbackUrl(string $token): string
    {
        $baseUrl = rtrim(
            (string) config(
                'services.qms.feedback_web_url',
                'https://portal-pre.nssf.go.tz/#/qms/feedback'
            ),
            '/'
        );

        return $baseUrl . '?token=' . urlencode($token);
    }
}
