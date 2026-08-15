<?php

namespace App\Services\Llm;

use App\Contracts\Llm\LlmClientInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient implements LlmClientInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 600): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error: '.$response->body());
        }

        return $response->json('choices.0.message.content') ?? '';
    }
}
