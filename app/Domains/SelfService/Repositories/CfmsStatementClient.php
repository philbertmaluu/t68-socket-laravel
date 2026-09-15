<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Repositories;

use App\Domains\SelfService\Support\SelfServiceLog;

class CfmsStatementClient
{
    public function __construct(
        private CfmsQmsClient $cfmsQmsClient
    ) {
    }

    /**
     * Valid member → GSD-style Sanctum session, then official statement PDF.
     */
    public function fetchPdfBase64(string $memberId, int $schemeId): string
    {
        $response = $this->cfmsQmsClient->get(
            "qms/member-statement/{$memberId}/{$schemeId}",
            $memberId,
            'Unable to load contribution statement'
        );

        if (!$response->successful()) {
            SelfServiceLog::warning('statement.cfms.http', [
                'member_id' => $memberId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Unable to load contribution statement');
        }

        $pdf = $this->extractBase64($response->json());
        if ($pdf === '') {
            throw new \RuntimeException('Contribution statement is empty');
        }

        $raw = base64_decode($pdf, true);
        if ($raw === false || !str_starts_with($raw, '%PDF')) {
            throw new \RuntimeException('Contribution statement is invalid');
        }

        SelfServiceLog::step('statement.cfms.ok', [
            'member_id' => $memberId,
            'scheme_id' => $schemeId,
            'bytes' => strlen($raw),
        ]);

        return $pdf;
    }

    private function extractBase64(mixed $payload): string
    {
        if (is_string($payload)) {
            return $this->stripPrefix($payload);
        }

        if (!is_array($payload)) {
            return '';
        }

        $candidates = [
            $payload['data'] ?? null,
            $payload['pdf'] ?? null,
            $payload['pdf_base64'] ?? null,
            $payload['statement'] ?? null,
            $payload['file'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $this->stripPrefix($candidate);
            }
            if (is_array($candidate)) {
                $nested = $this->extractBase64($candidate);
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return '';
    }

    private function stripPrefix(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, 'data:')) {
            $comma = strpos($value, ',');
            if ($comma !== false) {
                $value = substr($value, $comma + 1);
            }
        }

        return preg_replace('/\s+/', '', $value) ?? '';
    }
}
