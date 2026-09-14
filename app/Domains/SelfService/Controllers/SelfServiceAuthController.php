<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Controllers;

use App\Domains\Device\Models\Device;
use App\Domains\SelfService\Requests\SendOtpRequest;
use App\Domains\SelfService\Requests\VerifyMemberRequest;
use App\Domains\SelfService\Requests\VerifyOtpRequest;
use App\Domains\SelfService\Services\SelfServiceOtpService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceAuthController extends BaseController
{
    public function __construct(
        private SelfServiceOtpService $selfServiceOtpService
    ) {
    }

    public function verifyMember(VerifyMemberRequest $request): JsonResponse
    {
        try {
            $result = $this->selfServiceOtpService->verifyMember(
                (string) $request->validated()['member_number'],
                $this->device($request)
            );

            return $this->sendResponse($result, 'Member verified successfully');
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'Member not found' ? 404 : 422;

            return $this->sendError($e->getMessage(), [], $status);
        } catch (\Exception $e) {
            return $this->sendError('Failed to verify member', ['error' => $e->getMessage()], 500);
        }
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            $result = $this->selfServiceOtpService->sendOtp(
                (string) $payload['member_number'],
                $payload['challenge_id'] ?? null,
                $payload['locale'] ?? null,
                $this->device($request)
            );

            return $this->sendResponse($result, 'OTP sent successfully');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to send OTP', ['error' => $e->getMessage()], 500);
        }
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            $result = $this->selfServiceOtpService->verifyOtp(
                (string) $payload['member_number'],
                (string) $payload['otp'],
                $payload['challenge_id'] ?? null,
                $this->device($request)
            );

            return $this->sendResponse($result, 'OTP verified successfully');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to verify OTP', ['error' => $e->getMessage()], 500);
        }
    }

    public function memberDetails(Request $request): JsonResponse
    {
        $memberNumber = trim((string) $request->query('member_number', ''));
        if ($memberNumber === '') {
            return $this->sendError('Member number is required', [], 422);
        }

        try {
            $result = $this->selfServiceOtpService->memberDetails(
                $memberNumber,
                $this->device($request)
            );

            return $this->sendResponse($result, 'Member details retrieved successfully');
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'Member not found' ? 404 : 422;

            return $this->sendError($e->getMessage(), [], $status);
        } catch (\Exception $e) {
            return $this->sendError('Failed to load member details', ['error' => $e->getMessage()], 500);
        }
    }

    private function device(Request $request): ?Device
    {
        $device = $request->attributes->get('device') ?? $request->user();

        return $device instanceof Device ? $device : null;
    }
}
