<?php

namespace App\Domains\Mood\Controllers;

use App\Domains\Mood\Services\MoodFeedbackAdminService;
use App\Http\Controllers\BaseController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodFeedbackAdminController extends BaseController
{
    private MoodFeedbackAdminService $service;

    public function __construct()
    {
        $this->service = new MoodFeedbackAdminService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->service->listForCurrentOffice(
                $request->only(['type', 'search', 'rating_score'])
            );

            return $this->sendResponse($result, 'Mood feedbacks retrieved successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 500);
        }
    }
}
