<?php

namespace App\Domains\Bot\Services;

use App\Domains\Bot\Enums\BotRoleMode;
use App\Domains\Bot\Models\BotConversation;
use App\Domains\Bot\Models\BotToolCall;
use App\Domains\Authentication\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BotOrchestratorService
{
    private ToolRegistryService $toolRegistry;
    private McpServerService $mcpServerService;
    private OpenAiClientService $openAiClientService;

    public function __construct()
    {
        $this->toolRegistry = new ToolRegistryService();
        $this->mcpServerService = new McpServerService();
        $this->openAiClientService = new OpenAiClientService();
    }

    public function listTools(?User $user): array
    {
        $roleMode = $this->resolveRoleMode($user);
        return $this->mcpServerService->listTools($roleMode->value);
    }

    public function chat(?User $user, array $payload): array
    {
        $roleMode = $this->resolveRoleMode($user);
        $context = (array) ($payload['context'] ?? []);
        $scope = $this->buildScope($user, $roleMode, $context);

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($roleMode),
            ],
            [
                'role' => 'user',
                'content' => (string) $payload['message'],
            ],
        ];

        $tools = $this->toolRegistry->asOpenAiTools($roleMode->value);
        $firstPass = $this->openAiClientService->createChatCompletion($messages, $tools);
        $assistantMessage = Arr::get($firstPass, 'choices.0.message', []);

        $toolResults = [];
        $messages[] = $assistantMessage;

        foreach ((array) Arr::get($assistantMessage, 'tool_calls', []) as $toolCall) {
            $functionName = (string) Arr::get($toolCall, 'function.name', '');
            $rawArgs = (string) Arr::get($toolCall, 'function.arguments', '{}');
            $arguments = json_decode($rawArgs, true);
            if (!is_array($arguments)) {
                $arguments = [];
            }

            $result = $this->mcpServerService->callTool($functionName, $arguments, $scope);
            $toolResults[] = $result->toArray();

            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => Arr::get($toolCall, 'id'),
                'name' => $functionName,
                'content' => json_encode($result->payload),
            ];

            $this->logToolCall($user, $roleMode, $functionName, $arguments, $result->toArray());
        }

        $finalPass = !empty($toolResults)
            ? $this->openAiClientService->createChatCompletion($messages)
            : $firstPass;

        $answer = (string) Arr::get($finalPass, 'choices.0.message.content', 'No response generated.');
        $answer = $this->appendDataSafetyNote($answer, $toolResults);

        $conversation = $this->logConversation($user, $roleMode, (string) $payload['message'], $answer, $toolResults);

        return [
            'conversation_id' => (string) $conversation->id,
            'role_mode' => $roleMode->value,
            'answer' => $answer,
            'tool_calls' => $toolResults,
            'usage' => Arr::get($finalPass, 'usage', []),
        ];
    }

    private function resolveRoleMode(?User $user): BotRoleMode
    {
        if (!$user) {
            return BotRoleMode::CLERK;
        }

        $roleCodes = $user->userRoles()
            ->active()
            ->with('role:id,role_code')
            ->get()
            ->pluck('role.role_code')
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->values();

        if ($roleCodes->contains(fn ($code) => str_contains($code, 'SUPERVISOR'))) {
            return BotRoleMode::SUPERVISOR;
        }

        return BotRoleMode::CLERK;
    }

    private function buildScope(?User $user, BotRoleMode $roleMode, array $context): array
    {
        $contextOfficeId = $context['office_id'] ?? null;
        $clerkOfficeId = $this->resolveClerkOfficeId($user?->id);

        return [
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id !== null ? (int) $user->tenant_id : null,
            'office_id' => $contextOfficeId ?: ($clerkOfficeId ?: ($user?->office_id ?? null)),
            'role_mode' => $roleMode->value,
        ];
    }

    private function buildSystemPrompt(BotRoleMode $roleMode): string
    {
        if ($roleMode === BotRoleMode::SUPERVISOR) {
            return implode("\n", [
                'You are a queue-operations assistant for supervisors.',
                'Use tools for all operational metrics before making decisions.',
                'Never invent numbers.',
                'Provide concise recommendations with data-backed reasoning.',
            ]);
        }

        return implode("\n", [
            'You are a queue-operations assistant for clerks.',
            'Use tools to provide accurate ticket/service context.',
            'Never invent numbers.',
            'Give short actionable steps to speed up execution.',
        ]);
    }

    private function appendDataSafetyNote(string $answer, array $toolResults): string
    {
        if (empty($toolResults)) {
            return $answer . "\n\nNote: No data tool was called for this answer.";
        }

        return $answer;
    }

    private function logConversation(?User $user, BotRoleMode $roleMode, string $message, string $answer, array $toolCalls): BotConversation
    {
        return BotConversation::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'office_id' => $user?->office_id ?? null,
            'role_mode' => $roleMode->value,
            'message' => $message,
            'response' => $answer,
            'tool_calls_count' => count($toolCalls),
            'meta' => [
                'tool_names' => array_values(array_map(
                    fn ($tool) => $tool['tool'] ?? 'unknown',
                    $toolCalls,
                )),
            ],
        ]);
    }

    private function logToolCall(?User $user, BotRoleMode $roleMode, string $toolName, array $arguments, array $result): void
    {
        try {
            BotToolCall::create([
                'tenant_id' => $user?->tenant_id,
                'user_id' => $user?->id,
                'office_id' => $user?->office_id ?? null,
                'role_mode' => $roleMode->value,
                'tool_name' => $toolName,
                'arguments' => $this->redactSensitive($arguments),
                'result_payload' => $result['payload'] ?? [],
                'success' => (bool) ($result['success'] ?? false),
                'error_message' => $result['error'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Bot tool call logging failed', ['error' => $e->getMessage()]);
        }
    }

    private function redactSensitive(array $arguments): array
    {
        $redacted = $arguments;

        foreach (['phone', 'phone_number', 'member_number', 'password'] as $field) {
            if (array_key_exists($field, $redacted)) {
                $redacted[$field] = '[REDACTED]';
            }
        }

        return $redacted;
    }

    private function resolveClerkOfficeId(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $row = DB::table('counter_clerk as cc')
            ->join('counters as c', 'c.id', '=', 'cc.counter_id')
            ->where('cc.clerk_id', (string) $userId)
            ->where('cc.is_active', true)
            ->orderByDesc('cc.assigned_at')
            ->select('c.office_id')
            ->first();

        if (!$row || !isset($row->office_id)) {
            return null;
        }

        return (string) $row->office_id;
    }
}
