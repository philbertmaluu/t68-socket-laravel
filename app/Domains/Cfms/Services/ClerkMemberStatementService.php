<?php

declare(strict_types=1);

namespace App\Domains\Cfms\Services;

use App\Domains\Cfms\Repositories\CfmsDataManagementClient;
use App\Services\NotificationService;

class ClerkMemberStatementService
{
    public function __construct(
        private CfmsDataManagementClient $cfms,
        private NotificationService $notifications
    ) {
    }

    /**
     * @return array{member_number: string, scheme_id: int, view: string, pdf_base64: string}
     */
    public function fetch(string $memberNumber, int $schemeId, string $view): array
    {
        $memberId = preg_replace('/\D/', '', $memberNumber) ?? '';
        if ($memberId === '') {
            throw new \RuntimeException('Member number is required');
        }

        if (!in_array($schemeId, [1, 2], true)) {
            throw new \RuntimeException('Invalid scheme');
        }

        $normalizedView = $view === 'detailed' ? 'detailed' : 'statement';
        $pdf = $this->cfms->fetchMemberStatementPdf((int) $memberId, $schemeId, $normalizedView === 'detailed');

        return [
            'member_number' => $memberId,
            'scheme_id' => $schemeId,
            'view' => $normalizedView,
            'pdf_base64' => $pdf,
        ];
    }

    /**
     * @return array{success: bool, message: string, data: array|null}
     */
    public function emailStatement(string $email, string $pdfBase64, ?string $memberNumber = null, string $view = 'statement'): array
    {
        $recipient = strtolower(trim($email));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('A valid email address is required');
        }

        $raw = trim($pdfBase64);
        if (str_starts_with($raw, 'data:')) {
            $comma = strpos($raw, ',');
            $raw = $comma === false ? '' : substr($raw, $comma + 1);
        }
        $raw = preg_replace('/\s+/', '', $raw) ?? '';
        $decoded = base64_decode($raw, true);
        if ($raw === '' || $decoded === false || !str_starts_with($decoded, '%PDF')) {
            throw new \RuntimeException('A PDF statement is required');
        }

        $normalizedView = $view === 'detailed' ? 'detailed' : 'statement';
        $label = $normalizedView === 'detailed' ? 'detailed member statement' : 'member statement';
        $memberId = preg_replace('/\D/', '', (string) $memberNumber) ?? '';
        $subject = $memberId !== ''
            ? 'NSSF '.$label.' — '.$memberId
            : 'NSSF '.$label;
        $bodyText = $memberId !== ''
            ? "Please find attached your NSSF {$label} for member {$memberId}."
            : "Please find attached your NSSF {$label}.";
        $html = '<p>'.e($bodyText).'</p><p>Best regards,<br><strong>NSSF</strong></p>';
        $filename = $memberId !== ''
            ? 'nssf-'.$normalizedView.'-'.$memberId.'.pdf'
            : 'nssf-'.$normalizedView.'.pdf';

        $result = $this->notifications->sendEmail(
            $recipient,
            $html,
            $subject,
            $decoded,
            $filename,
            'application/pdf',
        );

        if (!$result['success']) {
            throw new \RuntimeException($result['message']);
        }

        return $result;
    }
}
