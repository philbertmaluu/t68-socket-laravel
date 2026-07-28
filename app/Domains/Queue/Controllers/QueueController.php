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
     * GET /api/qms/queues/{id}/activities/tickets?date=2026-07-28
     */
    public function activityTickets(Request $request, string $id): JsonResponse
    {
        try {
            $date = trim((string) $request->query('date', ''));
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $this->sendError('Invalid or missing date. Use YYYY-MM-DD.', [], 422);
            }

            $result = $this->service->queueActivityTickets($id, $date);

            return $this->sendResponse($result, 'Queue activity tickets retrieved successfully');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve queue activity tickets', ['error' => $e->getMessage()], 500);
        }
    }
}