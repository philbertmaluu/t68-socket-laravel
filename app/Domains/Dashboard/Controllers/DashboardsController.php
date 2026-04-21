<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\Dashboard\Services\DashboardService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

class DashboardsController extends BaseController
{
    private DashboardService $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    public function supervisorDashboard(): JsonResponse
    {
        try {
            $result = $this->service->supervisorDashboard();

            return $this->sendResponse($result, 'Supervisor dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve supervisor dashboard', ['error' => $e->getMessage()], 500);
        }
    }

    public function clerkDashboard(): JsonResponse
    {
        try {
            $result = $this->service->clerkDashboard();

            return $this->sendResponse($result, 'Clerk dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve clerk dashboard', ['error' => $e->getMessage()], 500);
        }
    }

    public function adminDashboard(): JsonResponse
    {
        try {
            $result = $this->service->adminDashboard();

            return $this->sendResponse($result, 'Admin dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve admin dashboard', ['error' => $e->getMessage()], 500);
        }
    }

    public function tenantDashboard(): JsonResponse
    {
        try {
            $result = $this->service->tenantDashboard();

            return $this->sendResponse($result, 'Tenant dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve tenant dashboard', ['error' => $e->getMessage()], 500);
        }
    }
}
