<?php

namespace App\Domains\Authentication\Controllers;

use App\Domains\Authentication\Requests\AuthenticateRequest;
use App\Domains\Authentication\Requests\RefreshTokenRequest;
use App\Domains\Authentication\Services\AuthService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    public function authenticate(AuthenticateRequest $request): JsonResponse
    {
        try {
            $result = $this->service->authenticate($request->validated()['token']);
            return $this->sendResponse($result, 'Authenticated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed Authentication.', ['error' => $e->getMessage()], 401);
        }
    }

    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->service->refreshToken($request->validated()['refresh_token']);
            return $this->sendResponse($result, 'Token refreshed successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Invalid Authorization.', ['error' => $e->getMessage()], 401);
        }
    }

    public function userDetails(): JsonResponse
    {
        try {
            $result = $this->service->getUserDetails();
            return $this->sendResponse($result, 'User details retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('User not found.', ['error' => $e->getMessage()], 404);
        }
    }

    public function userRoles(Request $request, string $module): JsonResponse
    {
        try {
            $result = $this->service->getUserRoles($module);
            return $this->sendResponse($result, 'Role details retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('User not found.', ['error' => $e->getMessage()], 404);
        }
    }

    public function transferRoles(): JsonResponse
    {
        try {
            $result = $this->service->getTransferRoles();
            return $this->sendResponse($result, 'Role details retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to get roles.', ['error' => $e->getMessage()], 500);
        }
    }

    public function moduleAccess(): JsonResponse
    {
        try {
            $result = $this->service->getModuleAccess();
            return $this->sendResponse($result, 'Module access retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to get module access.', ['error' => $e->getMessage()], 500);
        }
    }
}
