<?php

namespace App\Domains\Bot\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OpenAiClientService
{
    public function createChatCompletion(array $messages, array $tools = []): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new UnprocessableEntityHttpException('OpenAI API key is not configured');
        }

        $model = (string) config('services.openai.model', 'gpt-4o-mini');
        $timeout = (int) config('services.openai.timeout', 30);
        $maxTokens = (int) config('services.openai.max_output_tokens', 1200);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => $maxTokens,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->retry(2, 500)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (!$response->successful()) {
            throw new UnprocessableEntityHttpException('OpenAI request failed: ' . $response->body());
        }

        return $response->json();
    }
}
