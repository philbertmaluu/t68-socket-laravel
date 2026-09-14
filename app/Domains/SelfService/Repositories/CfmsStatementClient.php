<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Repositories;

use App\Domains\SelfService\Support\SelfServiceLog;
use Illuminate\Support\Facades\Http;
use Throwable;

class CfmsStatementClient
{
    /**
     * Fetches the CFMS contribution statement PDF (base64) for a member + scheme.
     */
    public function fetchPdfBase64(string $memberId, int $schemeId): string
    {
        $base = rtrim((string) config('self_service.cfms.api_base', 'https://cfmspre-api.nssf.go.tz'), '/');
        $token = (string) config('self_service.cfms.api_token', '');
        $timeout = (int) config('self_service.cfms.statement_timeout', 90);
        $url = "{$base}/api/data-management/member-statement/{$memberId}/{$schemeId}";

        SelfServiceLog::step('statement.cfms.request', [
            'member_id' => $memberId,
            'scheme_id' => $schemeId,
            'url' => $url,
        ]);

        try {
            $request = Http::acceptJson()->timeout($timeout);
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
        } catch (Throwable $e) {
            SelfServiceLog::warning('statement.cfms.failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Unable to load contribution statement');
        }

        if (!$response->successful()) {
            SelfServiceLog::warning('statement.cfms.http', [
                'member_id' => $memberId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Unable to load contribution statement');
        }

        $payload = $response->json();
        $pdf = $this->extractBase64($payload);
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
