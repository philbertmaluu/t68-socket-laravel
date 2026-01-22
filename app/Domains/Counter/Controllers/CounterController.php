<?php

namespace App\Domains\Counter\Controllers;

use App\Domains\Counter\Requests\StoreCounterRequest;
use App\Domains\Counter\Requests\UpdateCounterRequest;
use App\Domains\Counter\Services\CounterService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterController extends BaseController
{
    private CounterService $service;

    public function __construct()
    {
        $this->service = new CounterService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['status', 'type', 'service_id', 'office_id']);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Counters retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve counters', ['error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreCounterRequest $request): JsonResponse
    {
        try {
            $counter = $this->service->createCounter($request->validated());
            return $this->sendResponse($counter, 'Counter created successfully', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create counter', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $counter = $this->service->findById($id);

            if (!$counter) {
                return $this->sendError('Counter not found', [], 404);
            }

            return $this->sendResponse($counter, 'Counter retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve counter', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateCounterRequest $request, string $id): JsonResponse
    {
        try {
            $counter = $this->service->findById($id);

            if (!$counter) {
                return $this->sendError('Counter not found', [], 404);
            }

            $updated = $this->service->updateCounter($counter, $request->validated());
            return $this->sendResponse($updated, 'Counter updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update counter', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $counter = $this->service->findById($id);

            if (!$counter) {
                return $this->sendError('Counter not found', [], 404);
            }

            $this->service->deleteCounter($counter);
            return $this->sendResponse(null, 'Counter deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete counter', ['error' => $e->getMessage()], 500);
        }
    }
}
