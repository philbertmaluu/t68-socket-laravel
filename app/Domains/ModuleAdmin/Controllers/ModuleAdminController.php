<?php

namespace App\Domains\ModuleAdmin\Controllers;

use App\Domains\ModuleAdmin\Services\ModuleAdminService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleAdminController extends BaseController
{
    public function __construct(
        private ModuleAdminService $service
    ) {}

    // ------------------------------------------------------------------ Modules

    public function indexModules(): JsonResponse
    {
        return $this->sendResponse($this->service->listModules(), 'Modules retrieved');
    }

    public function storeModule(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        try {
            $module = $this->service->createModule($request->all(), $this->actingUserId($request));
            return $this->sendResponse($module, 'Module created', [], 201);
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function updateModule(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'code' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        try {
            $module = $this->service->updateModule($id, $request->all(), $this->actingUserId($request));
            return $this->sendResponse($module, 'Module updated');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function destroyModule(int $id): JsonResponse
    {
        try {
            $this->service->deleteModule($id);
            return $this->sendResponse([], 'Module deleted');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    // ------------------------------------------------------------------ Roles

    public function indexRoles(int $moduleId): JsonResponse
    {
        return $this->sendResponse($this->service->listRoles($moduleId), 'Roles retrieved');
    }

    public function storeRole(Request $request, int $moduleId): JsonResponse
    {
        $request->validate([
            'role_code' => 'required|string|max:50',
            'role_name' => 'required|string|max:200',
        ]);

        try {
            $role = $this->service->createRole($moduleId, $request->all(), $this->actingUserId($request));
            return $this->sendResponse($role, 'Role created', [], 201);
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function updateRole(Request $request, int $moduleId, int $roleId): JsonResponse
    {
        $request->validate([
            'role_code' => 'sometimes|string|max:50',
            'role_name' => 'sometimes|string|max:200',
        ]);

        try {
            $role = $this->service->updateRole($moduleId, $roleId, $request->all(), $this->actingUserId($request));
            return $this->sendResponse($role, 'Role updated');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function destroyRole(int $moduleId, int $roleId): JsonResponse
    {
        try {
            $this->service->deleteRole($moduleId, $roleId);
            return $this->sendResponse([], 'Role deleted');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    private function actingUserId(Request $request): int
    {
        return (int) optional($request->user())->id;
    }
}
