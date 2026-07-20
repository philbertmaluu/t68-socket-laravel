<?php

namespace App\Http\Middleware;

use App\Domains\Device\Models\Device;
use App\Domains\Mood\Models\MoodDeviceToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMoodDeviceAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Mood-Token')
            ?? $this->bearerToken($request);

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Mood device access token required',
            ], 401);
        }

        $tokenModel = MoodDeviceToken::where('access_token', $token)->first();

        if (!$tokenModel || $tokenModel->isAccessExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired access token',
            ], 401);
        }

        $device = $tokenModel->device;
        if (!$device || !$device->isMoodChecker()) {
            return response()->json([
                'success' => false,
                'message' => 'Mood device not found',
            ], 401);
        }

        $deviceUuid = $request->header('X-Device-UUID');
        if ($deviceUuid !== null && $device->device_uuid && $deviceUuid !== $device->device_uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Device UUID mismatch',
            ], 401);
        }

        $tokenModel->update(['last_used_at' => now()]);

        $request->attributes->set('mood_device', $device);
        $request->attributes->set('device', $device);
        $request->setUserResolver(fn () => $device);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }
}
