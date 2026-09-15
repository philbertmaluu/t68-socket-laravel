<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Services;

use App\Domains\SelfService\Repositories\CfmsClaimClient;
use App\Domains\SelfService\Support\SelfServiceLog;

class MemberClaimService
{
    public function __construct(
        private CfmsClaimClient $cfmsClaimClient
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function list(string $memberNumber): array
    {
        $memberId = $this->normalizeMemberNumber($memberNumber);

        SelfServiceLog::step('claims.list.start', ['member_number' => $memberId]);

        $payload = $this->cfmsClaimClient->listClaims($memberId);
        $claims = $payload['claims'] ?? [];
        if (!is_array($claims)) {
            $claims = [];
        }

        return [
            'member_number' => $memberId,
            'claims' => array_values($claims),
            'total' => is_numeric($payload['total'] ?? null) ? (int) $payload['total'] : count($claims),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $memberNumber, string $claimId): array
    {
        $memberId = $this->normalizeMemberNumber($memberNumber);
        $normalizedClaimId = trim($claimId);
        if ($normalizedClaimId === '') {
            throw new \RuntimeException('Claim number is required');
        }

        SelfServiceLog::step('claims.show.start', [
            'member_number' => $memberId,
            'claim_id' => $normalizedClaimId,
        ]);

        return $this->cfmsClaimClient->getClaim($memberId, $normalizedClaimId);
    }

    private function normalizeMemberNumber(string $memberNumber): string
    {
        $memberId = preg_replace('/\D/', '', $memberNumber) ?? '';
        if ($memberId === '') {
            throw new \RuntimeException('Member number is required');
        }

        return $memberId;
    }
}
