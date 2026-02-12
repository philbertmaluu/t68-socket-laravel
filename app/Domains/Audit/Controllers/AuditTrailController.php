<?php

namespace App\Domains\Audit\Controllers;

use App\Domains\Audit\Services\AuditTrailService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditTrailController extends BaseController
{
    private AuditTrailService $service;

    public function __construct()
    {
        $this->service = new AuditTrailService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only([
                'event',
                'auditable_type',
                'auditable_id',
                'user_id',
                'tenant_id',
                'date_from',
                'date_to',
            ]);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Audit trails retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve audit trails', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $auditTrail = $this->service->findById($id);

            if (!$auditTrail) {
                return $this->sendError('Audit trail not found', [], 404);
            }

            return $this->sendResponse($auditTrail, 'Audit trail retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve audit trail', ['error' => $e->getMessage()], 500);
        }
    }
}
