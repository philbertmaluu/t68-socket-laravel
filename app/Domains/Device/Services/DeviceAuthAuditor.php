<?php

namespace App\Domains\Device\Services;

use App\Domains\Audit\Models\AuditTrail;
use App\Domains\Device\Models\Device;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Writes device authentication attempts into audit_trails so admins can
 * diagnose kiosk/TV login failures (payload, IP, UA, reason).
 */
class DeviceAuthAuditor
{
    public const EVENT_SUCCESS = 'device_auth.success';
    public const EVENT_FAILED = 'device_auth.failed';
    public const TAG = 'device_auth';

    /**
     * @param  array<string, mixed>  $credentials  Raw request credentials
     * @param  array<string, mixed>  $extra        Optional context (client, validation errors, etc.)
     */
    public static function success(
        array $credentials,
        Device $device,
        array $extra = []
    ): void {
        self::write(
            event: self::EVENT_SUCCESS,
            credentials: $credentials,
            device: $device,
            reason: null,
            extra: $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $extra
     */
    public static function failed(
        array $credentials,
        string $reason,
        ?Device $device = null,
        array $extra = []
    ): void {
        self::write(
            event: self::EVENT_FAILED,
            credentials: $credentials,
            device: $device,
            reason: $reason,
            extra: $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $extra
     */
    private static function write(
        string $event,
        array $credentials,
        ?Device $device,
        ?string $reason,
        array $extra = []
    ): void {
        try {
            $method = !empty($credentials['device_key'])
                ? 'device_key'
                : 'name_password';

            $payload = self::sanitizePayload($credentials);

            AuditTrail::create([
                'tenant_id' => $device?->tenant_id
                    ?? (app()->bound('tenant.id') ? app('tenant.id') : null),
                // Unmatched attempts use id 0 so existing NOT NULL columns stay valid.
                'auditable_type' => Device::class,
                'auditable_id' => $device?->id ?? 0,
                'event' => $event,
                'user_id' => null,
                'user_type' => null,
                'old_values' => $reason !== null ? ['reason' => $reason] : null,
                'new_values' => array_filter([
                    'method' => $method,
                    'outcome' => $event === self::EVENT_SUCCESS ? 'success' : 'failed',
                    'reason' => $reason,
                    'device_id' => $device?->id,
                    'device_name' => $device?->name,
                    'device_type' => $device?->type,
                    'payload' => $payload,
                    'client' => self::resolveClientHint(),
                    'extra' => $extra !== [] ? $extra : null,
                ], static fn ($v) => $v !== null),
                'url' => Request::fullUrl(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'tags' => [self::TAG, $method],
            ]);
        } catch (\Throwable $e) {
            // Never break login because auditing failed.
            Log::error('Failed to write device auth audit trail', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);
        }
    }

    /**
     * Keep payloads useful for debugging; redact secrets only.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private static function sanitizePayload(array $credentials): array
    {
        $out = [];

        if (array_key_exists('device_key', $credentials)) {
            $raw = (string) ($credentials['device_key'] ?? '');
            $out['device_key'] = $raw;
            $out['device_key_normalized'] = strtoupper(trim($raw));
            $out['device_key_length'] = strlen(trim($raw));
        }

        if (array_key_exists('name', $credentials)) {
            $out['name'] = $credentials['name'];
        }

        if (array_key_exists('password', $credentials)) {
            $password = (string) ($credentials['password'] ?? '');
            $out['password'] = $password === '' ? '' : '[REDACTED]';
            $out['password_length'] = strlen($password);
            $out['password_present'] = $password !== '';
        }

        return $out;
    }

    private static function resolveClientHint(): string
    {
        $ua = strtolower((string) Request::userAgent());

        if (str_contains($ua, 'dart') || str_contains($ua, 'flutter')) {
            return 'flutter_kiosk';
        }
        if (str_contains($ua, 'electron')) {
            return 'electron_kiosk';
        }
        if (str_contains($ua, 'mozilla') || str_contains($ua, 'chrome')) {
            return 'browser';
        }

        return 'unknown';
    }
}
