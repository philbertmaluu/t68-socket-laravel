<?php

namespace App\Domains\Queue\Controllers;

use App\Http\Controllers\BaseController;
use App\Domains\Queue\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QueueController extends BaseController
{
    private QueueService $service;

    public function __construct()
    {
        $this->service = new QueueService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $officeId = $request->query('office_id');
            $queues = $this->service->getAllQueuesPerOffice(
                $officeId !== null ? (string) $officeId : null
            );
            return $this->sendResponse($queues, 'Queues retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve queues', ['error' => $e->getMessage()], 500);
        }
    }

}