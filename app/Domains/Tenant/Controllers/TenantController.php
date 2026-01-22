<?php

namespace App\Domains\Tenant\Controllers;

use App\Domains\Tenant\Requests\StoreTenantRequest;
use App\Domains\Tenant\Requests\UpdateTenantRequest;
use App\Domains\Tenant\Services\TenantService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends BaseController
{
    private TenantService $service;

    public function __construct()
    {
        $this->service = new TenantService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['is_active', 'domain']);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Tenants retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve tenants', ['error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        try {
            $tenant = $this->service->createTenant($request->validated());
            return $this->sendResponse($tenant, 'Tenant created successfully', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create tenant', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $tenant = $this->service->findById($id);

            if (!$tenant) {
                return $this->sendError('Tenant not found', [], 404);
            }

            return $this->sendResponse($tenant, 'Tenant retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve tenant', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        try {
            $tenant = $this->service->findById($id);

            if (!$tenant) {
                return $this->sendError('Tenant not found', [], 404);
            }

            $updated = $this->service->updateTenant($tenant, $request->validated());
            return $this->sendResponse($updated, 'Tenant updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update tenant', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $tenant = $this->service->findById($id);

            if (!$tenant) {
                return $this->sendError('Tenant not found', [], 404);
            }

            $this->service->deleteTenant($tenant);
            return $this->sendResponse(null, 'Tenant deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete tenant', ['error' => $e->getMessage()], 500);
        }
    }
}
