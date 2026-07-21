<?php

namespace App\Domains\Mood\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Device\Repositories\DeviceRepository;
use App\Domains\Mood\Models\MoodDeviceToken;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Support\Str;

class MoodAuthService
{
    private DeviceRepository $deviceRepository;

    public function __construct()
    {
        $this->deviceRepository = new DeviceRepository();
    }

    /**
     * @return array{device: array<string, mixed>, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function login(array $credentials): array
    {
        $deviceUuid = trim((string) ($credentials['device_uuid'] ?? ''));
        $device = $this->resolveDevice($credentials);

        $this->assertDeviceCanAuthenticate($device);

        return $this->issueSession($device, $deviceUuid);
    }

    public function logout(Device $device): void
    {
        TransactionHelper::execute(function () use ($device) {
            MoodDeviceToken::where('device_id', $device->id)->delete();
            $device->update(['status' => Device::STATUS_OFFLINE]);
        });
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function refreshToken(string $refreshToken): array
    {
        $tokenModel = MoodDeviceToken::where('refresh_token', $refreshToken)->first();

        if (!$tokenModel || $tokenModel->isRefreshExpired()) {
            throw new \RuntimeException('Invalid or expired refresh token');
        }

        $device = $tokenModel->device;
        if (!$device || !$device->isMoodChecker()) {
            throw new \RuntimeException('Device not found');
        }

        return TransactionHelper::execute(function () use ($tokenModel, $device) {
            $tokenModel->delete();

            $accessExpiresAt = now()->addDay();
            $refreshExpiresAt = now()->addDays(30);

            $newToken = MoodDeviceToken::create([
                'device_id' => $device->id,
                'access_token' => Str::random(64),
                'refresh_token' => Str::random(64),
                'device_uuid' => $device->device_uuid,
                'access_expires_at' => $accessExpiresAt,
                'refresh_expires_at' => $refreshExpiresAt,
            ]);

            return [
                'access_token' => $newToken->access_token,
                'refresh_token' => $newToken->refresh_token,
                'token_type' => 'Bearer',
                'expires_in' => now()->diffInSeconds($accessExpiresAt),
            ];
        });
    }

    public function heartbeat(Device $device): void
    {
        $device->update([
            'last_seen' => now(),
            'status' => Device::STATUS_ONLINE,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDevice(Device $device): array
    {
        return [
            'id' => (string) $device->id,
            'name' => (string) $device->name,
            'type' => (string) $device->type,
            'mode' => (string) ($device->mood_mode ?? Device::MOOD_MODE_GENERAL),
            'status' => (string) $device->status,
            'branch_id' => (string) $device->office_id,
            'region_id' => (string) $device->region_id,
            'counter_id' => $device->counter_id ? (string) $device->counter_id : null,
            'device_uuid' => (string) ($device->device_uuid ?? ''),
            'tenant_id' => (int) $device->tenant_id,
        ];
    }

    private function resolveDevice(array $credentials): Device
    {
        $deviceKey = strtoupper(trim((string) ($credentials['device_key'] ?? '')));
        $name = trim((string) ($credentials['name'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if ($deviceKey !== '') {
            $device = $this->deviceRepository->findByDeviceKey($deviceKey);
            if (!$device || !$device->isMoodChecker()) {
                throw new \RuntimeException('Invalid device credentials');
            }

            return $device;
        }

        if ($name === '' || $password === '') {
            throw new \InvalidArgumentException('Provide either device_key or both name and password');
        }

        $device = Device::withoutGlobalScope('tenant')
            ->where(function ($query) use ($name) {
                $query->where('name', $name)
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)]);
            })
            ->first();

        if (!$device || !$device->isMoodChecker()) {
            throw new \RuntimeException('Invalid device credentials');
        }

        $storedPlain = $device->decrypted_password;
        if ($storedPlain === null || $password !== $storedPlain) {
            throw new \RuntimeException('Invalid device credentials');
        }

        return $device;
    }

    private function assertDeviceCanAuthenticate(Device $device): void
    {
        if ($device->status === Device::STATUS_MAINTENANCE) {
            throw new \RuntimeException('Device is in maintenance and cannot authenticate');
        }

        if ($device->isMoodCounterMode() && empty($device->counter_id)) {
            throw new \RuntimeException('Counter mode device must have a counter assigned');
        }
    }

    /**
     * @return array{device: array<string, mixed>, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    private function issueSession(Device $device, string $deviceUuid): array
    {
        return TransactionHelper::execute(function () use ($device, $deviceUuid) {
            // Hard-delete so a concurrent login cannot leave the active token soft-deleted.
            MoodDeviceToken::withTrashed()
                ->where('device_id', $device->id)
                ->forceDelete();

            if ($deviceUuid !== '') {
                $device->update(['device_uuid' => $deviceUuid]);
            } elseif (empty($device->device_uuid)) {
                $device->update(['device_uuid' => (string) Str::uuid()]);
            }

            $device->refresh();

            $accessExpiresAt = now()->addDay();
            $refreshExpiresAt = now()->addDays(30);

            $token = MoodDeviceToken::create([
                'device_id' => $device->id,
                'access_token' => Str::random(64),
                'refresh_token' => Str::random(64),
                'device_uuid' => $device->device_uuid,
                'access_expires_at' => $accessExpiresAt,
                'refresh_expires_at' => $refreshExpiresAt,
            ]);

            $device->update([
                'status' => Device::STATUS_ONLINE,
                'last_seen' => now(),
            ]);

            return [
                'device' => $this->formatDevice($device),
                'access_token' => $token->access_token,
                'refresh_token' => $token->refresh_token,
                'token_type' => 'Bearer',
                'expires_in' => now()->diffInSeconds($accessExpiresAt),
            ];
        });
    }
}
