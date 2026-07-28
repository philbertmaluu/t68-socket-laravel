<?php

namespace App\Domains\Queue\Controllers;

use App\Domains\Queue\Services\QueueService;
use App\Http\Controllers\BaseController;
use App\Traits\UserOfficeTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends BaseController
{
    use UserOfficeTrait;

    private QueueService $service;

    public function __construct()
    {
        $this->service = new QueueService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $officeId = (string) $this->getUserOfficeAndRegionFromHrp()['office_id'];
            $queues = $this->service->getAllQueuesPerOffice($officeId);
            return $this->sendResponse($queues, 'Queues retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve queues', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/qms/queues/{id}/activities?year=2026
     */
    public function activities(Request $request, string $id): JsonResponse
    {
        try {
            $year = (int) $request->query('year', now()->year);
            $result = $this->service->queueActivities($id, $year);

            return $this->sendResponse($result, 'Queue activities retrieved successfully');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve queue activities', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/qms/queues/{id}/activities/tickets?date=YYYY-MM-DD
     * or ?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function activityTickets(Request $request, string $id): JsonResponse
    {
        try {
            [$date, $from, $to, $error] = $this->resolveDateFilters($request);
            if ($error !== null) {
                return $this->sendError($error, [], 422);
            }

            $result = $this->service->queueActivityTickets($id, $date, $from, $to);

            return $this->sendResponse($result, 'Queue activity tickets retrieved successfully');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve queue activity tickets', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/qms/queues/{id}/activities/export?format=pdf|excel
     * with date=YYYY-MM-DD or from=&to=
     */
    public function exportActivityTickets(Request $request, string $id)
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

            $export = $this->service->exportQueueActivityTickets($id, $format, $date, $from, $to);

            return response($export['content'], 200, [
                'Content-Type' => $export['mime'],
                'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to export queue activity tickets', ['error' => $e->getMessage()], 500);
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
