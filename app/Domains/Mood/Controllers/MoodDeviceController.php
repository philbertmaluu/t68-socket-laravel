<?php

namespace App\Domains\Mood\Controllers;

use App\Domains\Mood\Services\MoodAuthService;
use App\Domains\Mood\Services\MoodConfigurationService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodDeviceController extends BaseController
{
    private MoodAuthService $authService;
    private MoodConfigurationService $configService;

    public function __construct()
    {
        $this->authService = new MoodAuthService();
        $this->configService = new MoodConfigurationService();
    }

    public function device(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');

        return $this->sendResponse($this->authService->formatDevice($device), 'Device retrieved successfully');
    }

    public function configuration(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');
        $locale = $request->query('locale');

        return $this->sendResponse(
            $this->configService->getConfiguration($device, $locale),
            'Configuration retrieved successfully'
        );
    }

    public function theme(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');

        return $this->sendResponse($this->configService->getTheme($device, $request->query('locale')), 'Theme retrieved successfully');
    }

    public function languages(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');

        return $this->sendResponse($this->configService->getLanguages($device), 'Languages retrieved successfully');
    }

    public function messages(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');

        return $this->sendResponse($this->configService->getMessages($device, $request->query('locale')), 'Messages retrieved successfully');
    }

    public function company(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');

        return $this->sendResponse($this->configService->getCompany($device, $request->query('locale')), 'Company retrieved successfully');
    }

    public function ratingOptions(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');

        return $this->sendResponse($this->configService->getRatingOptions($device, $request->query('locale')), 'Rating options retrieved successfully');
    }

    public function feedbackReasons(Request $request): JsonResponse
    {
        $device = $request->attributes->get('mood_device');

        return $this->sendResponse($this->configService->getFeedbackReasons($device, $request->query('locale')), 'Feedback reasons retrieved successfully');
    }
}
