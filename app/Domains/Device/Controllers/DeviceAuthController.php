<?php

namespace App\Domains\Device\Controllers;

use App\Domains\Device\Models\Device;
use App\Domains\Device\Requests\DeviceAuthenticateRequest;
use App\Domains\Device\Services\DeviceService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceAuthController extends BaseController
{
    public function __construct(
        private DeviceService $deviceService
    ) {}

    /**
     * Authenticate device by device_key or by name + password.
     * Public route (no Sanctum). Returns device info and token.
     */
    public function authenticate(DeviceAuthenticateRequest $request): JsonResponse
    {
        $rawKey = $request->input('device_key');
        Log::info('device.auth.attempt', [
            'device_key' => $rawKey,
            'device_key_length' => is_string($rawKey) ? strlen(trim($rawKey)) : null,
            'name' => $request->input('name'),
            'has_password' => $request->filled('password'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_authorization' => $request->hasHeader('Authorization'),
        ]);

        try {
            $credentials = $request->only(['device_key', 'name', 'password']);
            $result = $this->deviceService->authenticateDevice($credentials);

            $device = $result['device'];
            $deviceData = $device->only([
                'id',
                'name',
                'type',
                'status',
                'region_id',
                'office_id',
                'serial_number',
                'ip_address',
                'last_seen',
            ]);
            $deviceData['last_seen'] = $device->last_seen?->toIso8601String();

            return $this->sendResponse([
                'device' => $deviceData,
                'token' => $result['token'],
            ], 'Device authenticated successfully');
        } catch (\Exception $e) {
            Log::warning('device.auth.failed', [
                'message' => $e->getMessage(),
                'device_key' => is_string($rawKey) ? $rawKey : null,
            ]);

            return $this->sendError($e->getMessage(), [], 401);
        }
    }

    /**
     * Update the authenticated device session (last_seen, status, ip_address).
     * Protected by device.auth middleware.
     */
    public function updateSession(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $data = $request->only(['status', 'ip_address']);
        $data['last_seen'] = now();
        $device->update($data);

        $deviceData = $device->only([
            'id',
            'name',
            'type',
            'status',
            'region_id',
            'office_id',
            'serial_number',
            'ip_address',
            'last_seen',
        ]);
        $deviceData['last_seen'] = $device->last_seen?->toIso8601String();

        return $this->sendResponse($deviceData, 'Device session updated');
    }
}
