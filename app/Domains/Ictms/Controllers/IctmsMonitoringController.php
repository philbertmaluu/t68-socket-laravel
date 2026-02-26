<?php

namespace App\Domains\Ictms\Controllers;

use App\Domains\Ictms\Services\IctmsMonitoringService;
use Illuminate\Http\JsonResponse;

class IctmsMonitoringController
{
    public function __construct(
        private IctmsMonitoringService $service
    ) {
    }

    /**
     * GET /api/ictms/service - Service health for ICTMS monitoring.
     */
    public function service(): JsonResponse
    {
        $data = $this->service->getServiceStatus();
        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/ictms/interface - Interface status for ICTMS monitoring.
     */
    public function interface(): JsonResponse
    {
        $data = $this->service->getInterfaceStatus();
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'success',
        ]);
    }
}
