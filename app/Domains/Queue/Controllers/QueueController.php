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

}