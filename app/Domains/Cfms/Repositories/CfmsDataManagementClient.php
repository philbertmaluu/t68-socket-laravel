<?php

declare(strict_types=1);

namespace App\Domains\Cfms\Repositories;

use Illuminate\Support\Facades\Http;
use Throwable;

class CfmsDataManagementClient
{
    public function fetchMemberStatementPdf(int $memberId, int $schemeId, bool $detailed): string
    {
        $token = trim((string) config('self_service.cfms.sanctum_token', ''));
        if ($token === '') {
            throw new \RuntimeException('CFMS statement service is not configured');
        }

        $path = $detailed
            ? "data-management/marked-member-statement/{$memberId}/{$schemeId}"
            : "data-management/member-statement/{$memberId}/{$schemeId}";

        $base = $this->apiBase();
        $url = $base.'/'.ltrim($path, '/');

        try {
            $response = Http::acceptJson()
                ->timeout($this->timeout())
                ->withToken($token)
                ->get($url);
        } catch (Throwable $e) {
            throw new \RuntimeException('Unable to load member statement');
        }

        if (!$response->successful()) {
            $message = data_get($response->json(), 'message');
            throw new \RuntimeException(
                is_string($message) && $message !== ''
                    ? $message
                    : 'Unable to load member statement'
            );
        }

        $payload = $response->json();
        if (is_array($payload) && array_key_exists('success', $payload) && $payload['success'] !== true) {
            $message = data_get($payload, 'message');
            throw new \RuntimeException(
                is_string($message) && $message !== ''
                    ? $message
                    : 'Unable to load member statement'
            );
        }

        $pdf = $this->extractBase64($payload);
        if ($pdf === '') {
            throw new \RuntimeException('Member statement is empty');
        }

        $raw = base64_decode($pdf, true);
        if ($raw === false || !str_starts_with($raw, '%PDF')) {
            throw new \RuntimeException('Member statement is invalid');
        }

        return $pdf;
    }

    public function apiBase(): string
    {
        $base = rtrim((string) config('self_service.cfms.api_base', 'https://cfmspro-api.nssf.go.tz/api'), '/');
        if (!str_ends_with(strtolower($base), '/api')) {
            $base .= '/api';
        }

        return $base;
    }

    public function timeout(): int
    {
        return (int) config('self_service.cfms.statement_timeout', 90);
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
