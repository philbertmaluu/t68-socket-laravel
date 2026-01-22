<?php

namespace App\Domains\Service\Controllers;

use App\Domains\Service\Requests\StoreServiceRequest;
use App\Domains\Service\Requests\UpdateServiceRequest;
use App\Domains\Service\Services\ServiceService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends BaseController
{
    private ServiceService $service;

    public function __construct()
    {
        $this->service = new ServiceService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['status', 'region_id', 'office_id']);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Services retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve services', ['error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            $service = $this->service->createService($request->validated());
            return $this->sendResponse($service, 'Service created successfully', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create service', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $service = $this->service->findById($id);

            if (!$service) {
                return $this->sendError('Service not found', [], 404);
            }

            return $this->sendResponse($service, 'Service retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve service', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateServiceRequest $request, string $id): JsonResponse
    {
        try {
            $service = $this->service->findById($id);

            if (!$service) {
                return $this->sendError('Service not found', [], 404);
            }

            $updated = $this->service->updateService($service, $request->validated());
            return $this->sendResponse($updated, 'Service updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update service', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $service = $this->service->findById($id);

            if (!$service) {
                return $this->sendError('Service not found', [], 404);
            }

            $this->service->deleteService($service);
            return $this->sendResponse(null, 'Service deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete service', ['error' => $e->getMessage()], 500);
        }
    }
}
