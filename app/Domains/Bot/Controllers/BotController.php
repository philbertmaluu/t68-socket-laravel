<?php

namespace App\Domains\Bot\Controllers;

use App\Domains\Bot\Requests\ChatRequest;
use App\Domains\Bot\Services\BotOrchestratorService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BotController extends BaseController
{
    private BotOrchestratorService $service;

    public function __construct()
    {
        $this->service = new BotOrchestratorService();
    }

    public function chat(ChatRequest $request): JsonResponse
    {
        try {
            $result = $this->service->chat(
                $request->user(),
                $request->validated(),
            );

            return $this->sendResponse($result, 'Bot response generated successfully');
        } catch (AccessDeniedHttpException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            return $this->sendError('Failed to generate bot response', ['error' => $e->getMessage()], 500);
        }
    }

    public function tools(Request $request): JsonResponse
    {
        try {
            $tools = $this->service->listTools($request->user());
            return $this->sendResponse($tools, 'Bot tools retrieved successfully');
        } catch (\Throwable $e) {
            return $this->sendError('Failed to retrieve tools', ['error' => $e->getMessage()], 500);
        }
    }
}
