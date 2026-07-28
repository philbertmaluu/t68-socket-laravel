<?php

namespace App\Domains\Report\Controllers;

use App\Domains\Report\Services\ReportService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseController
{
    private ReportService $service;

    public function __construct()
    {
        $this->service = new ReportService();
    }

    /**
     * GET /api/qms/reports/queue-summary?from=&to=
     */
    public function queueSummary(Request $request): JsonResponse
    {
        return $this->runRangeReport($request, fn (string $from, string $to) => $this->service->queueSummary($from, $to), 'Queue summary retrieved successfully');
    }

    /**
     * GET /api/qms/reports/daily-trends?from=&to=
     */
    public function dailyTrends(Request $request): JsonResponse
    {
        return $this->runRangeReport($request, fn (string $from, string $to) => $this->service->dailyTrends($from, $to), 'Daily trends retrieved successfully');
    }

    /**
     * GET /api/qms/reports/service-mix?from=&to=
     */
    public function serviceMix(Request $request): JsonResponse
    {
        return $this->runRangeReport($request, fn (string $from, string $to) => $this->service->serviceMix($from, $to), 'Service mix retrieved successfully');
    }

    /**
     * GET /api/qms/reports/hourly-activity?from=&to=
     */
    public function hourlyActivity(Request $request): JsonResponse
    {
        return $this->runRangeReport($request, fn (string $from, string $to) => $this->service->hourlyActivity($from, $to), 'Hourly activity retrieved successfully');
    }

    /**
     * GET /api/qms/reports/counter-performance?from=&to=
     */
    public function counterPerformance(Request $request): JsonResponse
    {
        return $this->runRangeReport($request, fn (string $from, string $to) => $this->service->counterPerformance($from, $to), 'Counter performance retrieved successfully');
    }

    /**
     * GET /api/qms/reports/clerk-performance?from=&to=
     */
    public function clerkPerformance(Request $request): JsonResponse
    {
        return $this->runRangeReport($request, fn (string $from, string $to) => $this->service->clerkPerformance($from, $to), 'Clerk performance retrieved successfully');
    }

    /**
     * GET /api/qms/reports/tickets?from=&to=&date=&hour=&serviceType=&counterName=&clerkName=&limit=
     */
    public function tickets(Request $request): JsonResponse
    {
        try {
            [$from, $to, $error] = $this->resolveRange($request);
            if ($error !== null) {
                return $this->sendError($error, [], 422);
            }

            $filters = [
                'date' => $request->query('date'),
                'hour' => $request->query('hour'),
                'serviceType' => $request->query('serviceType'),
                'counterName' => $request->query('counterName'),
                'clerkName' => $request->query('clerkName'),
                'limit' => $request->query('limit'),
            ];

            $result = $this->service->tickets($from, $to, $filters);

            return $this->sendResponse($result, 'Report tickets retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve report tickets', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param callable(string, string): array<string, mixed> $callback
     */
    private function runRangeReport(Request $request, callable $callback, string $successMessage): JsonResponse
    {
        try {
            [$from, $to, $error] = $this->resolveRange($request);
            if ($error !== null) {
                return $this->sendError($error, [], 422);
            }

            return $this->sendResponse($callback($from, $to), $successMessage);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function resolveRange(Request $request): array
    {
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        if ($from === '' || $to === '' || !preg_match($datePattern, $from) || !preg_match($datePattern, $to)) {
            return [null, null, 'Invalid or missing date range. Use from=YYYY-MM-DD&to=YYYY-MM-DD.'];
        }

        if ($from > $to) {
            return [null, null, 'Invalid date range. "from" must be on or before "to".'];
        }

        return [$from, $to, null];
    }
}
