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

    /**
     * Actual tickets for a selected day (drawer when clicking the activity graph).
     * GET /api/qms/dashboard/office-activities/tickets?date=2026-07-28
     */
    public function officeActivityTickets(Request $request): JsonResponse
    {
        try {
            $date = trim((string) $request->query('date', ''));
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $this->sendError('Invalid or missing date. Use YYYY-MM-DD.', [], 422);
            }

            $result = $this->service->officeActivityTickets($date);

            return $this->sendResponse($result, 'Office activity tickets retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve office activity tickets', ['error' => $e->getMessage()], 500);
        }
    }
}
