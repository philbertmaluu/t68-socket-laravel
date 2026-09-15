<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Repositories;

use App\Domains\SelfService\Support\SelfServiceLog;

class CfmsClaimClient
{
    public function __construct(
        private CfmsQmsClient $cfmsQmsClient
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function listClaims(string $memberId): array
    {
        $response = $this->cfmsQmsClient->get(
            "qms/claims/{$memberId}",
            $memberId,
            'Unable to load claims'
        );

        if ($response->status() === 404) {
            throw new \RuntimeException('Member not found');
        }

        if (!$response->successful()) {
            SelfServiceLog::warning('claims.cfms.list_http', [
                'member_id' => $memberId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Unable to load claims');
        }

        $data = data_get($response->json(), 'data');
        if (!is_array($data)) {
            throw new \RuntimeException('Unable to load claims');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClaim(string $memberId, string $claimId): array
    {
        $response = $this->cfmsQmsClient->get(
            "qms/claims/{$memberId}/{$claimId}",
            $memberId,
            'Unable to load claim'
        );

        if ($response->status() === 404) {
            throw new \RuntimeException('Claim not found');
        }

        if (!$response->successful()) {
            SelfServiceLog::warning('claims.cfms.show_http', [
                'member_id' => $memberId,
                'claim_id' => $claimId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Unable to load claim');
        }

        $data = data_get($response->json(), 'data');
        if (!is_array($data)) {
            throw new \RuntimeException('Unable to load claim');
        }

        return $data;
    }
}
