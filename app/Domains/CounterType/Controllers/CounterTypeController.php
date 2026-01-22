<?php

namespace App\Domains\CounterType\Controllers;

use App\Domains\CounterType\Requests\StoreCounterTypeRequest;
use App\Domains\CounterType\Requests\UpdateCounterTypeRequest;
use App\Domains\CounterType\Services\CounterTypeService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterTypeController extends BaseController
{
    private CounterTypeService $service;

    public function __construct()
    {
        $this->service = new CounterTypeService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['status', 'code']);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Counter types retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve counter types', ['error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreCounterTypeRequest $request): JsonResponse
    {
        try {
            $counterType = $this->service->createCounterType($request->validated());
            return $this->sendResponse($counterType, 'Counter type created successfully', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create counter type', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $counterType = $this->service->findById($id);

            if (!$counterType) {
                return $this->sendError('Counter type not found', [], 404);
            }

            return $this->sendResponse($counterType, 'Counter type retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve counter type', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateCounterTypeRequest $request, string $id): JsonResponse
    {
        try {
            $counterType = $this->service->findById($id);

            if (!$counterType) {
                return $this->sendError('Counter type not found', [], 404);
            }

            $updated = $this->service->updateCounterType($counterType, $request->validated());
            return $this->sendResponse($updated, 'Counter type updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update counter type', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $counterType = $this->service->findById($id);

            if (!$counterType) {
                return $this->sendError('Counter type not found', [], 404);
            }

            $this->service->deleteCounterType($counterType);
            return $this->sendResponse(null, 'Counter type deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete counter type', ['error' => $e->getMessage()], 500);
        }
    }
}
