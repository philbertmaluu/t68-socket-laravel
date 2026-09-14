<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Repositories;

use App\Domains\SelfService\Support\SelfServiceLog;
use Illuminate\Support\Facades\Http;
use Throwable;

class CfmsStatementClient
{
    /**
     * Valid member → GSD-style Sanctum session, then official statement PDF.
     */
    public function fetchPdfBase64(string $memberId, int $schemeId): string
    {
        $base = $this->apiBase();
        $timeout = (int) config('self_service.cfms.statement_timeout', 90);
        $token = $this->issueMemberSession($memberId, $base, $timeout);
        $url = "{$base}/qms/member-statement/{$memberId}/{$schemeId}";

        SelfServiceLog::step('statement.cfms.request', [
            'member_id' => $memberId,
            'scheme_id' => $schemeId,
            'url' => $url,
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->withToken($token)
                ->get($url);
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

    private function issueMemberSession(string $memberId, string $base, int $timeout): string
    {
        SelfServiceLog::step('statement.cfms.session', ['member_id' => $memberId]);

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->post("{$base}/qms/session", [
                    'member_id' => (int) $memberId,
                ]);
        } catch (Throwable $e) {
            SelfServiceLog::warning('statement.cfms.session_failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Unable to create CFMS member session');
        }

        $token = data_get($response->json(), 'data.token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            SelfServiceLog::warning('statement.cfms.session_http', [
                'member_id' => $memberId,
                'status' => $response->status(),
                'message' => data_get($response->json(), 'message'),
            ]);
            throw new \RuntimeException('Unable to create CFMS member session');
        }

        return $token;
    }

    private function apiBase(): string
    {
        $base = rtrim((string) config('self_service.cfms.api_base', 'https://cfmspro-api.nssf.go.tz/api'), '/');
        if (!str_ends_with(strtolower($base), '/api')) {
            $base .= '/api';
        }

        return $base;
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
