<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Services;

use App\Domains\SelfService\Repositories\CfmsStatementClient;
use App\Domains\SelfService\Support\SelfServiceLog;

class MemberStatementService
{
    public function __construct(
        private CfmsStatementClient $cfmsStatementClient
    ) {
    }

    /**
     * @return array{member_number: string, scheme_id: int, pdf_base64: string}
     */
    public function fetch(string $memberNumber, ?int $schemeId = null): array
    {
        $memberId = preg_replace('/\D/', '', $memberNumber) ?? '';
        if ($memberId === '') {
            throw new \RuntimeException('Member number is required');
        }

        $scheme = $schemeId ?? (int) config('self_service.cfms.default_scheme_id', 1);
        if (!in_array($scheme, [1, 2], true)) {
            throw new \RuntimeException('Invalid scheme');
        }

        SelfServiceLog::step('statement.start', [
            'member_number' => $memberId,
            'scheme_id' => $scheme,
        ]);

        $pdf = $this->cfmsStatementClient->fetchPdfBase64($memberId, $scheme);

        return [
            'member_number' => $memberId,
            'scheme_id' => $scheme,
            'pdf_base64' => $pdf,
        ];
    }
}
