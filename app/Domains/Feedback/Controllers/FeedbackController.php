<?php

namespace App\Domains\Feedback\Controllers;

use App\Domains\Device\Models\Device;
use App\Domains\Feedback\Requests\FeedbackContextRequest;
use App\Domains\Feedback\Requests\SubmitFeedbackRequest;
use App\Domains\Feedback\Requests\TicketFeedbackQrRequest;
use App\Domains\Feedback\Services\FeedbackService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends BaseController
{
    private FeedbackService $service;

    public function __construct()
    {
        $this->service = new FeedbackService();
    }

    public function submit(SubmitFeedbackRequest $request): JsonResponse
    {
        try {
            $feedback = $this->service->submitFeedback($request->validated());
            return $this->sendResponse($feedback, 'Feedback submitted successfully', [], 201);
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to submit feedback', ['error' => $e->getMessage()], 500);
        }
    }

    public function context(FeedbackContextRequest $request): JsonResponse
    {
        try {
            $context = $this->service->getContextFromToken((string) $request->validated()['token']);
            return $this->sendResponse($context, 'Feedback context resolved successfully');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to resolve feedback context', ['error' => $e->getMessage()], 500);
        }
    }

    public function generateGeneralQr(Request $request): JsonResponse
    {
        try {
            /** @var Device|null $device */
            $device = $request->attributes->get('device') ?? $request->user();
            if (!$device) {
                return $this->sendError('Authenticated device not found', [], 401);
            }

            $result = $this->service->generateGeneralFeedbackUrlForDevice($device);
            return $this->sendResponse($result, 'General feedback QR generated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate general feedback QR', ['error' => $e->getMessage()], 500);
        }
    }

    public function generateTicketQr(TicketFeedbackQrRequest $request, Request $rawRequest): JsonResponse
    {
        try {
            /** @var Device|null $device */
            $device = $rawRequest->attributes->get('device') ?? $rawRequest->user();
            if (!$device) {
                return $this->sendError('Authenticated device not found', [], 401);
            }

            $result = $this->service->generateTicketFeedbackUrlForDevice($device, (string) $request->validated()['ticket_id']);
            return $this->sendResponse($result, 'Ticket feedback QR generated successfully');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate ticket feedback QR', ['error' => $e->getMessage()], 500);
        }
    }
}
