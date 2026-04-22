<?php

namespace App\Domains\Bot\Services;

use App\Domains\Bot\DTOs\ToolCallResult;
use App\Domains\Bot\Repositories\BotAnalyticsRepository;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class McpServerService
{
    private ToolRegistryService $toolRegistry;
    private BotAnalyticsRepository $repository;

    public function __construct()
    {
        $this->toolRegistry = new ToolRegistryService();
        $this->repository = new BotAnalyticsRepository();
    }

    public function listTools(string $roleMode): array
    {
        return $this->toolRegistry->byRole($roleMode);
    }

    public function callTool(string $name, array $arguments, array $scope): ToolCallResult
    {
        $tool = $this->toolRegistry->find($name);
        if (!$tool) {
            throw new UnprocessableEntityHttpException("Unknown tool: {$name}");
        }

        if (!in_array($scope['role_mode'], $tool['allowed_roles'], true)) {
            throw new AccessDeniedHttpException('Role is not allowed to call this tool');
        }

        $this->validateArguments($tool['input_schema'], $arguments);

        $scopedOfficeId = $this->resolveOfficeScope(
            $scope['role_mode'],
            $scope['office_id'] ?? null,
            $arguments['office_id'] ?? null,
        );

        $tenantId = $scope['tenant_id'] ?? null;

        return match ($name) {
            'queue_snapshot' => new ToolCallResult($name, true, $this->repository->queueSnapshot($scopedOfficeId, $tenantId)),
            'wait_time_trend' => new ToolCallResult($name, true, $this->repository->waitTimeTrend(
                $scopedOfficeId,
                $tenantId,
                (int) ($arguments['hours'] ?? 8),
            )),
            'ticket_context' => new ToolCallResult($name, true, $this->repository->ticketContext(
                (string) $arguments['ticket_number'],
                $tenantId,
            )),
            'clerk_workload' => new ToolCallResult($name, true, $this->repository->clerkWorkload(
                $scopedOfficeId,
                $tenantId,
                (int) ($arguments['limit'] ?? 5),
            )),
            'service_requirements' => new ToolCallResult($name, true, $this->repository->serviceRequirements(
                (string) $arguments['service_id'],
                $tenantId,
            )),
            default => throw new UnprocessableEntityHttpException("Unsupported tool: {$name}"),
        };
    }

    private function validateArguments(array $schema, array $arguments): void
    {
        $rules = [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        foreach ($properties as $field => $config) {
            $fieldRules = [];
            $fieldRules[] = in_array($field, $required, true) ? 'required' : 'nullable';

            $type = $config['type'] ?? 'string';
            $fieldRules[] = $type === 'integer' ? 'integer' : 'string';

            if (isset($config['minimum'])) {
                $fieldRules[] = 'min:' . (int) $config['minimum'];
            }
            if (isset($config['maximum'])) {
                $fieldRules[] = 'max:' . (int) $config['maximum'];
            }

            $rules[$field] = $fieldRules;
        }

        Validator::make($arguments, $rules)->validate();
    }

    private function resolveOfficeScope(string $roleMode, ?string $userOfficeId, ?string $requestedOfficeId): ?string
    {
        if ($roleMode === 'clerk') {
            if (!$userOfficeId) {
                throw new AccessDeniedHttpException('Clerk office scope is not available');
            }

            if ($requestedOfficeId && $userOfficeId !== $requestedOfficeId) {
                throw new AccessDeniedHttpException('Clerk cannot query another office');
            }
        }

        return $requestedOfficeId ?: $userOfficeId;
    }
}
