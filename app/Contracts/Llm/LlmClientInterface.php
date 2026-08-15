<?php

namespace App\Contracts\Llm;

interface LlmClientInterface
{
    /**
     * Send a prompt and return the raw text completion.
     */
    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 600): string;
}
