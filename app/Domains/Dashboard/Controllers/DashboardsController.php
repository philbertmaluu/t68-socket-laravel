<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\Dashboard\Services\DashboardService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * GitHub-style heatmap feed: tickets per office per date for a given year.
     * GET /api/qms/dashboard/office-activities?year=2026
     */
    public function officeActivities(Request $request): JsonResponse
    {
        try {
            $year = (int) $request->query('year', now()->year);
            $result = $this->service->officeActivities($year);

            return $this->sendResponse($result, 'Office activities retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve office activities', ['error' => $e->getMessage()], 500);
        }
    }
}
