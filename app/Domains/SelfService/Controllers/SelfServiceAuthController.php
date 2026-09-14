<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Controllers;

use App\Domains\Device\Models\Device;
use App\Domains\SelfService\Requests\SendOtpRequest;
use App\Domains\SelfService\Requests\VerifyMemberRequest;
use App\Domains\SelfService\Requests\VerifyOtpRequest;
use App\Domains\SelfService\Services\MemberStatementService;
use App\Domains\SelfService\Services\SelfServiceOtpService;
use App\Domains\SelfService\Support\SelfServiceLog;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceAuthController extends BaseController
{
    public function __construct(
        private SelfServiceOtpService $selfServiceOtpService,
        private MemberStatementService $memberStatementService
    ) {
    }

    public function verifyMember(VerifyMemberRequest $request): JsonResponse
    {
        $device = $this->device($request);
        SelfServiceLog::step('http.verify_member', [
            'member_number' => $request->input('member_number'),
            'device_id' => $device?->id,
            'ip' => $request->ip(),
        ]);

        try {
            $result = $this->selfServiceOtpService->verifyMember(
                (string) $request->validated()['member_number'],
                $device
            );

            SelfServiceLog::step('http.verify_member.ok', [
                'challenge_id' => $result['challenge_id'] ?? null,
                'masked_phone' => $result['masked_phone'] ?? null,
            ]);

            return $this->sendResponse($result, 'Member verified successfully');
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'Member not found' ? 404 : 422;
            SelfServiceLog::error('http.verify_member.failed', $e, ['status' => $status]);

            return $this->sendError($e->getMessage(), [], $status);
        } catch (\Exception $e) {
            SelfServiceLog::error('http.verify_member.exception', $e);

            return $this->sendError('Failed to verify member', ['error' => $e->getMessage()], 500);
        }
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $device = $this->device($request);
        SelfServiceLog::step('http.send_otp', [
            'member_number' => $payload['member_number'] ?? null,
            'challenge_id' => $payload['challenge_id'] ?? null,
            'locale' => $payload['locale'] ?? null,
            'device_id' => $device?->id,
        ]);

        try {
            $result = $this->selfServiceOtpService->sendOtp(
                (string) $payload['member_number'],
                $payload['challenge_id'] ?? null,
                $payload['locale'] ?? null,
                $device
            );

            SelfServiceLog::step('http.send_otp.ok', [
                'challenge_id' => $result['challenge_id'] ?? null,
            ]);

            return $this->sendResponse($result, 'OTP sent successfully');
        } catch (\RuntimeException $e) {
            SelfServiceLog::error('http.send_otp.failed', $e);

            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            SelfServiceLog::error('http.send_otp.exception', $e);

            return $this->sendError('Failed to send OTP', ['error' => $e->getMessage()], 500);
        }
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $device = $this->device($request);
        SelfServiceLog::step('http.verify_otp', [
            'member_number' => $payload['member_number'] ?? null,
            'challenge_id' => $payload['challenge_id'] ?? null,
            'otp' => $payload['otp'] ?? null,
            'device_id' => $device?->id,
        ]);

        try {
            $result = $this->selfServiceOtpService->verifyOtp(
                (string) $payload['member_number'],
                (string) $payload['otp'],
                $payload['challenge_id'] ?? null,
                $device
            );

            SelfServiceLog::step('http.verify_otp.ok', [
                'challenge_id' => $result['challenge_id'] ?? null,
            ]);

            return $this->sendResponse($result, 'OTP verified successfully');
        } catch (\RuntimeException $e) {
            SelfServiceLog::error('http.verify_otp.failed', $e);

            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            SelfServiceLog::error('http.verify_otp.exception', $e);

            return $this->sendError('Failed to verify OTP', ['error' => $e->getMessage()], 500);
        }
    }

    public function memberDetails(Request $request): JsonResponse
    {
        $memberNumber = trim((string) $request->query('member_number', ''));
        SelfServiceLog::step('http.member_details', [
            'member_number' => $memberNumber,
            'device_id' => $this->device($request)?->id,
        ]);

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
            SelfServiceLog::error('http.member_details.failed', $e);

            return $this->sendError($e->getMessage(), [], $status);
        } catch (\Exception $e) {
            SelfServiceLog::error('http.member_details.exception', $e);

            return $this->sendError('Failed to load member details', ['error' => $e->getMessage()], 500);
        }
    }

    public function contributionStatement(Request $request): JsonResponse
    {
        $memberNumber = trim((string) $request->query('member_number', ''));
        $schemeId = $request->query('scheme_id');
        SelfServiceLog::step('http.contribution_statement', [
            'member_number' => $memberNumber,
            'scheme_id' => $schemeId,
            'device_id' => $this->device($request)?->id,
        ]);

        if ($memberNumber === '') {
            return $this->sendError('Member number is required', [], 422);
        }

        try {
            $result = $this->memberStatementService->fetch(
                $memberNumber,
                $schemeId === null || $schemeId === '' ? null : (int) $schemeId
            );

            return $this->sendResponse($result, 'Contribution statement retrieved successfully');
        } catch (\RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'Member not found' => 404,
                'Member number is required', 'Invalid scheme' => 422,
                default => 502,
            };
            SelfServiceLog::error('http.contribution_statement.failed', $e);

            return $this->sendError($e->getMessage(), [], $status);
        } catch (\Exception $e) {
            SelfServiceLog::error('http.contribution_statement.exception', $e);

            return $this->sendError('Failed to load contribution statement', ['error' => $e->getMessage()], 500);
        }
    }

    private function device(Request $request): ?Device
    {
        $device = $request->attributes->get('device') ?? $request->user();

        return $device instanceof Device ? $device : null;
    }
}
