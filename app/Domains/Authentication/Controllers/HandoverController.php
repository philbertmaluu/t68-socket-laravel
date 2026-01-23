<?php

namespace App\Domains\Authentication\Controllers;

use App\Domains\Authentication\Requests\CompleteHandoverRequest;
use App\Domains\Authentication\Requests\InitiateHandoverRequest;
use App\Domains\Authentication\Services\HandoverService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HandoverController extends BaseController
{
    private HandoverService $service;

    public function __construct()
    {
        $this->service = new HandoverService();
    }

    public function initiate(InitiateHandoverRequest $request): JsonResponse
    {
        try {
            $userId = Auth::user()->id;
            $handovers = $this->service->initiateHandover(
                $userId,
                $request->validated()['role_ids'],
                $request->validated()['to_user_id'],
                $request->validated()['notes'] ?? null
            );

            $handovers = collect($handovers)->map(function ($handover) {
                return $handover->load(['fromUser', 'toUser', 'role']);
            });

            return $this->sendResponse($handovers, 'Handover initiated successfully.', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to initiate handover', ['error' => $e->getMessage()], 500);
        }
    }

    public function complete(CompleteHandoverRequest $request, int $id): JsonResponse
    {
        try {
            $this->service->completeHandover($id);
            return $this->sendResponse(null, 'Handover completed successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to complete handover', ['error' => $e->getMessage()], 500);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $this->service->cancelHandover($id);
            return $this->sendResponse(null, 'Handover cancelled successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to cancel handover', ['error' => $e->getMessage()], 500);
        }
    }

    public function active(): JsonResponse
    {
        try {
            $userId = Auth::user()->id;
            $handovers = $this->service->getActiveHandovers($userId);
            return $this->sendResponse($handovers, 'Active handovers retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve active handovers', ['error' => $e->getMessage()], 500);
        }
    }

    public function history(): JsonResponse
    {
        try {
            $userId = Auth::user()->id;
            $handovers = $this->service->getHandoverHistory($userId);
            return $this->sendResponse($handovers, 'Handover history retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve handover history', ['error' => $e->getMessage()], 500);
        }
    }
}
