<?php

namespace App\Domains\Mood\Controllers;

use App\Domains\Mood\Requests\SubmitMoodCounterFeedbackRequest;
use App\Domains\Mood\Requests\SubmitMoodGeneralFeedbackRequest;
use App\Domains\Mood\Requests\SubmitMoodOfflineSyncRequest;
use App\Domains\Mood\Services\MoodConfigurationService;
use App\Domains\Mood\Services\MoodCounterFeedbackService;
use App\Domains\Mood\Services\MoodFeedbackSessionService;
use App\Domains\Mood\Services\MoodGeneralFeedbackService;
use App\Domains\Mood\Services\MoodOfflineSyncService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodFeedbackController extends BaseController
{
    private MoodGeneralFeedbackService $generalService;
    private MoodCounterFeedbackService $counterService;
    private MoodFeedbackSessionService $sessionService;
    private MoodOfflineSyncService $offlineService;
    private MoodConfigurationService $configService;

    public function __construct()
    {
        $this->generalService = new MoodGeneralFeedbackService();
        $this->counterService = new MoodCounterFeedbackService();
        $this->sessionService = new MoodFeedbackSessionService();
        $this->offlineService = new MoodOfflineSyncService();
        $this->configService = new MoodConfigurationService();
    }

    public function submitGeneral(SubmitMoodGeneralFeedbackRequest $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('mood_device');
            $feedback = $this->generalService->submit($device, $request->validated());

            return $this->sendResponse($feedback, 'General feedback submitted successfully', [], 201);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function generalReasons(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');

        return $this->sendResponse(
            $this->configService->getGeneralReasons($device, $request->query('locale')),
            'General feedback reasons retrieved successfully'
        );
    }

    public function pendingSession(Request $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('mood_device');
            $session = $this->sessionService->getPendingSession($device);

            if (!$session) {
                return $this->sendResponse(null, 'No pending session');
            }

            return $this->sendResponse($this->sessionService->formatSession($session), 'Pending session retrieved');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function submitCounter(SubmitMoodCounterFeedbackRequest $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('mood_device');
            $feedback = $this->counterService->submit($device, $request->validated());

            return $this->sendResponse($feedback, 'Counter feedback submitted successfully', [], 201);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function expireSession(Request $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('mood_device');
            $sessionId = (string) $request->input('session_id', '');

            $session = $sessionId !== ''
                ? \App\Domains\Mood\Models\MoodFeedbackSession::where('id', $sessionId)->where('device_id', $device->id)->first()
                : $this->sessionService->getPendingSession($device);

            if (!$session) {
                return $this->sendError('Session not found', [], 404);
            }

            $expired = $this->sessionService->expireSession($session);

            return $this->sendResponse($this->sessionService->formatSession($expired), 'Session expired');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function completeSession(Request $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('mood_device');
            $sessionId = (string) $request->input('session_id', '');

            $session = \App\Domains\Mood\Models\MoodFeedbackSession::where('id', $sessionId)
                ->where('device_id', $device->id)
                ->first();

            if (!$session) {
                return $this->sendError('Session not found', [], 404);
            }

            $completed = $this->sessionService->completeSession($session);

            return $this->sendResponse($this->sessionService->formatSession($completed), 'Session completed');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function offlineSync(SubmitMoodOfflineSyncRequest $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('mood_device');
            $result = $this->offlineService->syncBatch($device, $request->validated('items'));

            return $this->sendResponse($result, 'Offline sync processed');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }
}
