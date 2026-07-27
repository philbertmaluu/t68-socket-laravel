<?php

namespace App\Http\Middleware;

use App\Domains\Device\Models\Device;
use App\Domains\Device\Models\DeviceToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceAuthenticated
{
    /**
     * Handle an incoming request. Validate device token from X-Device-Token or Authorization: Bearer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token')
            ?? $this->bearerToken($request);

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Device token required',
            ], 401);
        }

        $tokenModel = DeviceToken::where('token', $token)->first();

        if (!$tokenModel || $tokenModel->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired device token',
            ], 401);
        }

        $device = Device::withoutGlobalScope('tenant')->find($tokenModel->device_id);
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 401);
        }

        $tokenModel->update(['last_used_at' => now()]);

        if (!empty($device->tenant_id)) {
            app()->instance('tenant.id', $device->tenant_id);
        }

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
