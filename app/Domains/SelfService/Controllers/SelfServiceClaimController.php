<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Controllers;

use App\Domains\Device\Models\Device;
use App\Domains\SelfService\Services\MemberClaimService;
use App\Domains\SelfService\Support\SelfServiceLog;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceClaimController extends BaseController
{
    public function __construct(
        private MemberClaimService $memberClaimService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $memberNumber = trim((string) $request->query('member_number', ''));
        SelfServiceLog::step('http.claims.list', [
            'member_number' => $memberNumber,
            'device_id' => $this->device($request)?->id,
        ]);

        if ($memberNumber === '') {
            return $this->sendError('Member number is required', [], 422);
        }

        try {
            $result = $this->memberClaimService->list($memberNumber);

            return $this->sendResponse($result, 'Claims retrieved successfully');
        } catch (\RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'Member not found', 'Claim not found' => 404,
                'Member number is required', 'Claim number is required' => 422,
                default => 502,
            };
            SelfServiceLog::error('http.claims.list.failed', $e);

            return $this->sendError($e->getMessage(), [], $status);
        } catch (\Exception $e) {
            SelfServiceLog::error('http.claims.list.exception', $e);

            return $this->sendError('Failed to load claims', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, string $claimId): JsonResponse
    {
        $memberNumber = trim((string) $request->query('member_number', ''));
        SelfServiceLog::step('http.claims.show', [
            'member_number' => $memberNumber,
            'claim_id' => $claimId,
            'device_id' => $this->device($request)?->id,
        ]);

        if ($memberNumber === '') {
            return $this->sendError('Member number is required', [], 422);
        }

        try {
            $result = $this->memberClaimService->show($memberNumber, $claimId);

            return $this->sendResponse($result, 'Claim retrieved successfully');
        } catch (\RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'Member not found', 'Claim not found' => 404,
                'Member number is required', 'Claim number is required' => 422,
                default => 502,
            };
            SelfServiceLog::error('http.claims.show.failed', $e);

            return $this->sendError($e->getMessage(), [], $status);
        } catch (\Exception $e) {
            SelfServiceLog::error('http.claims.show.exception', $e);

            return $this->sendError('Failed to load claim', ['error' => $e->getMessage()], 500);
        }
    }

    private function device(Request $request): ?Device
    {
        $device = $request->attributes->get('device') ?? $request->user();

        return $device instanceof Device ? $device : null;
    }
}
