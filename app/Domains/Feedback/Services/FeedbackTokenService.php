<?php

namespace App\Domains\Feedback\Services;

use Illuminate\Support\Str;

class FeedbackTokenService
{
    public function createGeneralToken(string $tenantId, ?string $officeId = null, int $ttlMinutes = 60 * 24 * 30): string
    {
        $payload = [
            'type' => 'general',
            'tenant_id' => (string) $tenantId,
            'office_id' => $officeId !== null ? (string) $officeId : null,
            'exp' => now()->addMinutes($ttlMinutes)->timestamp,
            'nonce' => Str::random(16),
        ];

        return $this->signPayload($payload);
    }

    public function createTicketToken(
        string $tenantId,
        string $ticketId,
        string $ticketNumber,
        ?string $clerkId = null,
        ?string $officeId = null,
        int $ttlMinutes = 60 * 24 * 14
    ): string {
        $payload = [
            'type' => 'ticket',
            'tenant_id' => (string) $tenantId,
            'ticket_id' => (string) $ticketId,
            'ticket_number' => (string) $ticketNumber,
            'clerk_id' => $clerkId !== null ? (string) $clerkId : null,
            'office_id' => $officeId !== null ? (string) $officeId : null,
            'exp' => now()->addMinutes($ttlMinutes)->timestamp,
            'nonce' => Str::random(16),
        ];

        return $this->signPayload($payload);
    }

    public function verifyToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new \RuntimeException('Invalid feedback token format');
        }

        [$payloadEncoded, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payloadEncoded, $this->getSigningKey());
        if (!hash_equals($expectedSignature, $signature)) {
            throw new \RuntimeException('Invalid feedback token signature');
        }

        $payloadJson = base64_decode(strtr($payloadEncoded, '-_', '+/'), true);
        if ($payloadJson === false) {
            throw new \RuntimeException('Invalid feedback token payload encoding');
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid feedback token payload');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp <= 0 || now()->timestamp > $exp) {
            throw new \RuntimeException('Feedback token expired');
        }

        return $payload;
    }

    private function signPayload(array $payload): string
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new \RuntimeException('Unable to encode feedback token payload');
        }

        $payloadEncoded = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payloadEncoded, $this->getSigningKey());

        return $payloadEncoded . '.' . $signature;
    }

    private function getSigningKey(): string
    {
        $appKey = (string) config('app.key', '');
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            return $decoded !== false ? $decoded : $appKey;
        }

        return $appKey;
    }
}
