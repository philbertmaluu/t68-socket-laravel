<?php

namespace App\Domains\Device\Controllers;

use App\Domains\Device\Requests\StoreDeviceRequest;
use App\Domains\Device\Requests\UpdateDeviceRequest;
use App\Domains\Device\Services\DeviceService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends BaseController
{
    private DeviceService $service;

    public function __construct()
    {
        $this->service = new DeviceService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['status', 'type', 'region_id', 'office_id', 'serial_number']);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Devices retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve devices', ['error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        try {
            $device = $this->service->createDevice($request->validated());
            return $this->sendResponse($device, 'Device created successfully', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create device', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $device = $this->service->findById($id);

            if (!$device) {
                return $this->sendError('Device not found', [], 404);
            }

            return $this->sendResponse($device, 'Device retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve device', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateDeviceRequest $request, string $id): JsonResponse
    {
        try {
            $device = $this->service->findById($id);

            if (!$device) {
                return $this->sendError('Device not found', [], 404);
            }

            $updated = $this->service->updateDevice($device, $request->validated());
            return $this->sendResponse($updated, 'Device updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update device', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $device = $this->service->findById($id);

            if (!$device) {
                return $this->sendError('Device not found', [], 404);
            }

            $this->service->deleteDevice($device);
            return $this->sendResponse(null, 'Device deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete device', ['error' => $e->getMessage()], 500);
        }
    }
}
