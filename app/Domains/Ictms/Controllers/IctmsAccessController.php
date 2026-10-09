<?php

namespace App\Domains\Ictms\Controllers;

use App\Domains\Ictms\Services\IctmsAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IctmsAccessController
{
    public function __construct(
        private IctmsAccessService $service
    ) {
    }

    /**
     * ICTMS-style success response.
     */
    private function success(mixed $data, string $message = 'Success', int $httpStatus = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status_code' => 1,
            'data' => $data,
            'message' => $message,
        ], $httpStatus);
    }

    /**
     * ICTMS-style error response.
     */
    private function error(string $message, int $httpStatus = 400, int $statusCode = 0): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status_code' => $statusCode,
            'message' => $message,
        ], $httpStatus);
    }

    /**
     * GET /api/modules - List system modules.
     */
    public function modules(): JsonResponse
    {
        if (!config('services.ictms.access_enabled', true)) {
            return $this->error('ICTMS access integration is disabled', 403);
        }
        $data = $this->service->getModules();
        return $this->success($data, 'ICTMS Module');
    }

    /**
     * POST /api/module/roles - Roles per module.
     */
    public function moduleRoles(Request $request): JsonResponse
    {
        if (!config('services.ictms.access_enabled', true)) {
            return $this->error('ICTMS access integration is disabled', 403);
        }
        $moduleId = (int) $request->input('moduleId', 0);
        if ($moduleId <= 0) {
            return $this->error('moduleId is required', 400);
        }
        $data = $this->service->getRolesByModule($moduleId);
        return $this->success($data, 'Role');
    }

    /**
     * GET /api/user/by-pfno?pfno= - Find or create user from HRP by PFNO (for UI before assign-role).
     */
    public function userByPfno(Request $request): JsonResponse
    {
        if (!config('services.ictms.access_enabled', true)) {
            return $this->error('ICTMS access integration is disabled', 403);
        }
        $pfno = $request->query('pfno');
        if ($pfno === null || trim((string) $pfno) === '') {
            return $this->error('pfno is required', 400);
        }
        $data = $this->service->getOrEnsureUserByPfno(trim((string) $pfno));
        return $this->success($data, $data['created'] ? 'User created from HRP' : 'User found');
    }

    /**
     * POST /api/assign-role - Assign role(s) to user(s). Used by ICTMS and other systems; do not change.
     * Users are found or created from HRP if they do not exist.
     */
    public function assignRole(Request $request): JsonResponse
    {
        if (!config('services.ictms.access_enabled', true)) {
            return $this->error('ICTMS access integration is disabled', 403);
        }
        $payload = $request->all();
        if (empty($payload)) {
            return $this->error('Payload is required', 400);
        }
        try {
            $this->service->assignRole($payload);
            return $this->success([], 'Role assigned successfully');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/assign-roles - Assign multiple roles to user(s). For QMS UI; accepts ROLE_IDS or array of items.
     */
    public function assignRoles(Request $request): JsonResponse
    {
        if (!config('services.ictms.access_enabled', true)) {
            return $this->error('ICTMS access integration is disabled', 403);
        }
        $payload = $request->all();
        if (empty($payload)) {
            return $this->error('Payload is required', 400);
        }
        try {
            $this->service->assignRolesToUser($payload);
            return $this->success([], 'Roles assigned successfully');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/user/roles - Users grouped by module.
     */
    public function userRoles(): JsonResponse
    {
        if (!config('services.ictms.access_enabled', true)) {
            return $this->error('ICTMS access integration is disabled', 403);
        }
        $data = $this->service->getUsersGroupedByModule();
        return $this->success($data, 'List of Users');
    }

    /**
     * POST /api/module/users - All roles for one user.
     */
    public function moduleUsers(Request $request): JsonResponse
    {
        if (!config('services.ictms.access_enabled', true)) {
            return $this->error('ICTMS access integration is disabled', 403);
        }
        $pfno = $request->input('pfno');
        if ($pfno === null || $pfno === '') {
            return $this->error('pfno is required', 400);
        }
        $data = $this->service->getUserRoles((string) $pfno);
        return $this->success($data, 'User Role');
    }

    /**
     * POST /api/access/revoke - Revoke role from user.
     */
    public function revokeAccess(Request $request): JsonResponse
    {
        if (!config('services.ictms.access_enabled', true)) {
            return $this->error('ICTMS access integration is disabled', 403);
        }
        $pfno = $request->input('pfno');
        $roleId = $request->input('role_id');
        if (empty($pfno) || empty($roleId)) {
            return $this->error('pfno and role_id are required', 400);
        }
        $updated = $this->service->revokeAccess(
            (string) $pfno,
            (string) $roleId,
            $request->input('updated_by')
        );
        if (!$updated) {
            return $this->error('User or role assignment not found', 404);
        }
        return $this->success([], 'Access revoked successfully');
    }
}
