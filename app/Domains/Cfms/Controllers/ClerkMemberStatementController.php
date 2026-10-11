<?php

declare(strict_types=1);

namespace App\Domains\Cfms\Controllers;

use App\Domains\Cfms\Services\ClerkMemberStatementService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClerkMemberStatementController extends BaseController
{
    public function __construct(
        private ClerkMemberStatementService $statements
    ) {
    }

    public function show(Request $request, string $member_id, string $scheme_id): JsonResponse
    {
        $view = strtolower(trim((string) $request->query('view', 'statement')));
        if (!in_array($view, ['statement', 'detailed'], true)) {
            return $this->sendError('Invalid view. Use statement or detailed.', [], 422);
        }

        try {
            $result = $this->statements->fetch($member_id, (int) $scheme_id, $view);

            return $this->sendResponse($result, 'Member statement retrieved successfully');
        } catch (\RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'Member number is required', 'Invalid scheme' => 422,
                'CFMS statement service is not configured' => 503,
                default => 502,
            };

            return $this->sendError($e->getMessage(), [], $status);
        } catch (\Exception $e) {
            return $this->sendError('Failed to load member statement', ['error' => $e->getMessage()], 500);
        }
    }
}
