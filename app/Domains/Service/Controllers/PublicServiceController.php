<?php

namespace App\Domains\Service\Controllers;

use App\Domains\Device\Models\Device;
use App\Domains\Service\Services\ServiceService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API: list services for kiosk (no Sanctum auth).
 * GET /api/qms/public/services?office_id=1&per_page=500
 */
class PublicServiceController extends BaseController
{
    private ServiceService $service;

    public function __construct()
    {
        $this->service = new ServiceService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 500);
            $page = (int) $request->get('page', 1);
            /** @var Device|null $device */
            $device = $request->user();
            if (!$device || empty($device->office_id)) {
                return $this->sendError('Unable to resolve office from authenticated device', [], 422);
            }

            $officeId = (string) $device->office_id;
            $result = $this->service->listPublic($perPage, $page, $officeId);

            return $this->sendResponse($result['data'], 'Services retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve services', ['error' => $e->getMessage()], 500);
        }
    }
}
