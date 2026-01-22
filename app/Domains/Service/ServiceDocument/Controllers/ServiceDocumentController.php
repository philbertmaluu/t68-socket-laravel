<?php

namespace App\Domains\Service\ServiceDocument\Controllers;

use App\Domains\Service\ServiceDocument\Requests\StoreServiceDocumentRequest;
use App\Domains\Service\ServiceDocument\Requests\UpdateServiceDocumentRequest;
use App\Domains\Service\ServiceDocument\Services\ServiceDocumentService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceDocumentController extends BaseController
{
    private ServiceDocumentService $service;

    public function __construct()
    {
        $this->service = new ServiceDocumentService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['service_id', 'is_required']);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Service documents retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve service documents', ['error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreServiceDocumentRequest $request): JsonResponse
    {
        try {
            $serviceDocument = $this->service->createServiceDocument($request->validated());
            return $this->sendResponse($serviceDocument, 'Service document created successfully', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create service document', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $serviceDocument = $this->service->findById($id);

            if (!$serviceDocument) {
                return $this->sendError('Service document not found', [], 404);
            }

            return $this->sendResponse($serviceDocument, 'Service document retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve service document', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateServiceDocumentRequest $request, string $id): JsonResponse
    {
        try {
            $serviceDocument = $this->service->findById($id);

            if (!$serviceDocument) {
                return $this->sendError('Service document not found', [], 404);
            }

            $updated = $this->service->updateServiceDocument($serviceDocument, $request->validated());
            return $this->sendResponse($updated, 'Service document updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update service document', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $serviceDocument = $this->service->findById($id);

            if (!$serviceDocument) {
                return $this->sendError('Service document not found', [], 404);
            }

            $this->service->deleteServiceDocument($serviceDocument);
            return $this->sendResponse(null, 'Service document deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete service document', ['error' => $e->getMessage()], 500);
        }
    }
}
