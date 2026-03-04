<?php

namespace App\Domains\Notification\Controllers;

use App\Domains\Notification\Requests\StoreNotificationTemplateRequest;
use App\Domains\Notification\Requests\UpdateNotificationTemplateRequest;
use App\Domains\Notification\Services\NotificationTemplateService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends BaseController
{
    private NotificationTemplateService $service;

    public function __construct()
    {
        $this->service = new NotificationTemplateService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['channel', 'locale', 'active', 'search']);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Notification templates retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve notification templates', ['error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $template = $this->service->create($data);
            return $this->sendResponse($template, 'Notification template created successfully', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create notification template', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $template = $this->service->findById($id);

            if (!$template) {
                return $this->sendError('Notification template not found', [], 404);
            }

            return $this->sendResponse($template, 'Notification template retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve notification template', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateNotificationTemplateRequest $request, string $id): JsonResponse
    {
        try {
            $template = $this->service->findById($id);

            if (!$template) {
                return $this->sendError('Notification template not found', [], 404);
            }

            $updated = $this->service->update($template, $request->validated());
            return $this->sendResponse($updated, 'Notification template updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update notification template', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $template = $this->service->findById($id, true);

            if (!$template) {
                return $this->sendError('Notification template not found', [], 404);
            }

            $this->service->delete($template);
            return $this->sendResponse(null, 'Notification template deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete notification template', ['error' => $e->getMessage()], 500);
        }
    }
}

