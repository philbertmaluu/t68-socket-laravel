<?php

declare(strict_types=1);

namespace App\Domains\Cfms\Services;

use App\Domains\Cfms\Repositories\CfmsDataManagementClient;

class ClerkMemberStatementService
{
    public function __construct(
        private CfmsDataManagementClient $cfms
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
}
