<?php

namespace App\Domains\Service\Controllers;

use App\Domains\Service\Requests\StoreServiceRequest;
use App\Domains\Service\Requests\UpdateServiceRequest;
use App\Domains\Service\Services\ServiceService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServiceController extends BaseController
{
    private ServiceService $service;

    public function __construct()
    {
        $this->service = new ServiceService();
    }

    public function catalog(): JsonResponse
    {
        try {
            $catalog = $this->service->listCatalogForCurrentOffice();
            return $this->sendResponse($catalog, 'Service catalog retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve service catalog', ['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['status', 'region_id']);

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
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create service', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $service = $this->service->findAssignedForCurrentOffice($id);

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
            $catalog = $this->service->findById($id);

            if (!$catalog) {
                return $this->sendError('Service not found', [], 404);
            }

            $updated = $this->service->updateService($catalog, $request->validated());
            return $this->sendResponse($updated, 'Service updated successfully');
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to update service', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $catalog = $this->service->findById($id);

            if (!$catalog) {
                return $this->sendError('Service not found', [], 404);
            }

            $deleted = $this->service->deleteService($catalog);
            if (!$deleted) {
                return $this->sendError('Service is not assigned to your office', [], 404);
            }

            return $this->sendResponse(null, 'Service deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete service', ['error' => $e->getMessage()], 500);
        }
    }
}
