<?php

namespace App\Enums\Llm;

enum LlmProvider: string
{
    case Claude = 'claude';
    case OpenAi = 'openai';

    public function label(): string
    {
        return match ($this) {
            self::Claude => 'Anthropic Claude',
            self::OpenAi => 'OpenAI',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::Claude => 'claude-sonnet-4-6',
            self::OpenAi => 'gpt-4o-mini',
        };
    }
}
