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
     * GET /api/qms/dashboard/office-activities/tickets?date=YYYY-MM-DD
     * or ?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function officeActivityTickets(Request $request): JsonResponse
    {
        try {
            [$date, $from, $to, $error] = $this->resolveDateFilters($request);
            if ($error !== null) {
                return $this->sendError($error, [], 422);
            }

            $result = $this->service->officeActivityTickets($date, $from, $to);

            return $this->sendResponse($result, 'Office activity tickets retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve office activity tickets', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/qms/dashboard/office-activities/export?format=pdf|excel
     * with date=YYYY-MM-DD or from=&to=
     */
    public function exportOfficeActivityTickets(Request $request)
    {
        try {
            [$date, $from, $to, $error] = $this->resolveDateFilters($request);
            if ($error !== null) {
                return $this->sendError($error, [], 422);
            }

            $format = strtolower(trim((string) $request->query('format', 'pdf')));
            if (!in_array($format, ['pdf', 'excel'], true)) {
                return $this->sendError('Invalid format. Use pdf or excel.', [], 422);
            }

            $export = $this->service->exportOfficeActivityTickets($format, $date, $from, $to);

            return response($export['content'], 200, [
                'Content-Type' => $export['mime'],
                'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to export office activity tickets', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: string|null, 3: string|null}
     */
    private function resolveDateFilters(Request $request): array
    {
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        $date = trim((string) $request->query('date', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $hasRange = $from !== '' || $to !== '';
        if ($hasRange) {
            if ($from === '' || $to === '' || !preg_match($datePattern, $from) || !preg_match($datePattern, $to)) {
                return [null, null, null, 'Invalid date range. Use from=YYYY-MM-DD&to=YYYY-MM-DD.'];
            }
            if ($from > $to) {
                return [null, null, null, 'Invalid date range. "from" must be on or before "to".'];
            }

            return [null, $from, $to, null];
        }

        if ($date === '' || !preg_match($datePattern, $date)) {
            return [null, null, null, 'Provide date=YYYY-MM-DD or from=&to= date range.'];
        }

        return [$date, null, null, null];
    }
}
