<?php

namespace App\Domains\Bot\DTOs;

class ToolCallResult
{
    public function __construct(
        public readonly string $toolName,
        public readonly bool $success,
        public readonly array $payload,
        public readonly ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'tool' => $this->toolName,
            'success' => $this->success,
            'payload' => $this->payload,
            'error' => $this->error,
        ];
    }
}
