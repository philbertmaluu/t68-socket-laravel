<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Repositories;

use App\Domains\SelfService\Support\SelfServiceLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class CfmsQmsClient
{
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

    public function issueMemberSession(string $memberId): string
    {
        $base = $this->apiBase();
        $timeout = $this->timeout();

        SelfServiceLog::step('cfms.qms.session', ['member_id' => $memberId]);

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->post("{$base}/qms/session", [
                    'member_id' => (int) $memberId,
                ]);
        } catch (Throwable $e) {
            SelfServiceLog::warning('cfms.qms.session_failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Unable to create CFMS member session');
        }

        $token = data_get($response->json(), 'data.token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            SelfServiceLog::warning('cfms.qms.session_http', [
                'member_id' => $memberId,
                'status' => $response->status(),
                'message' => data_get($response->json(), 'message'),
            ]);
            throw new \RuntimeException('Unable to create CFMS member session');
        }

        return $token;
    }

    public function get(string $path, string $memberId, string $failureMessage): Response
    {
        $base = $this->apiBase();
        $timeout = $this->timeout();
        $token = $this->issueMemberSession($memberId);
        $url = $base.'/'.ltrim($path, '/');

        SelfServiceLog::step('cfms.qms.get', [
            'member_id' => $memberId,
            'url' => $url,
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->withToken($token)
                ->get($url);
        } catch (Throwable $e) {
            SelfServiceLog::warning('cfms.qms.get_failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException($failureMessage);
        }

        return $response;
    }
}
