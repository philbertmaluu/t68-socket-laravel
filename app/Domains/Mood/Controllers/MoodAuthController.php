<?php

namespace App\Domains\Mood\Controllers;

use App\Domains\Mood\Requests\MoodLoginRequest;
use App\Domains\Mood\Requests\MoodRefreshTokenRequest;
use App\Domains\Mood\Services\MoodAuthService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodAuthController extends BaseController
{
    private MoodAuthService $authService;

    public function __construct()
    {
        $this->authService = new MoodAuthService();
    }

    public function login(MoodLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return $this->sendResponse($result, 'Mood device authenticated successfully');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('mood_device');
            $this->authService->logout($device);

            return $this->sendResponse(null, 'Logged out successfully');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function refreshToken(MoodRefreshTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->refreshToken((string) $request->validated('refresh_token'));

            return $this->sendResponse($result, 'Token refreshed successfully');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 401);
        }
    }

    public function heartbeat(Request $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('mood_device');
            $this->authService->heartbeat($device);

            return $this->sendResponse(['status' => 'ok'], 'Heartbeat received');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 500);
        }
    }
}
