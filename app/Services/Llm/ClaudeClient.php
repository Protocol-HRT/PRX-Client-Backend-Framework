<?php

namespace App\Services\Llm;

use App\Contracts\Llm\LlmClientInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClaudeClient implements LlmClientInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 600): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Claude API error: '.$response->body());
        }

        return $response->json('content.0.text') ?? '';
    }
}
