<?php

namespace App\Domains\Bot\Services;

use App\Domains\Bot\Enums\BotRoleMode;

class ToolRegistryService
{
    public function all(): array
    {
        return [
            [
                'name' => 'queue_snapshot',
                'description' => 'Return queue snapshot metrics for an office.',
                'allowed_roles' => [BotRoleMode::SUPERVISOR->value, BotRoleMode::CLERK->value],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'office_id' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'wait_time_trend',
                'description' => 'Return wait-time trend points for an office in a recent window.',
                'allowed_roles' => [BotRoleMode::SUPERVISOR->value],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'office_id' => ['type' => 'string'],
                        'hours' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 24],
                    ],
                ],
            ],
            [
                'name' => 'ticket_context',
                'description' => 'Return context details for a ticket number.',
                'allowed_roles' => [BotRoleMode::SUPERVISOR->value, BotRoleMode::CLERK->value],
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['ticket_number'],
                    'properties' => [
                        'ticket_number' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'clerk_workload',
                'description' => 'Return aggregated workload per clerk for the office.',
                'allowed_roles' => [BotRoleMode::SUPERVISOR->value],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'office_id' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                ],
            ],
            [
                'name' => 'service_requirements',
                'description' => 'Return required and optional documents for a service.',
                'allowed_roles' => [BotRoleMode::SUPERVISOR->value, BotRoleMode::CLERK->value],
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['service_id'],
                    'properties' => [
                        'service_id' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }

    public function byRole(string $roleMode): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $tool) => in_array($roleMode, $tool['allowed_roles'], true),
        ));
    }

    public function find(string $name): ?array
    {
        foreach ($this->all() as $tool) {
            if ($tool['name'] === $name) {
                return $tool;
            }
        }

        return null;
    }

    public function asOpenAiTools(string $roleMode): array
    {
        $tools = [];
        foreach ($this->byRole($roleMode) as $tool) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['input_schema'],
                ],
            ];
        }

        return $tools;
    }
}
