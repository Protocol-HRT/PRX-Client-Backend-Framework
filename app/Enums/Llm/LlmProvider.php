<?php

namespace App\Enums\Llm;

use Filament\Support\Contracts\HasLabel;

enum LlmProvider: string implements HasLabel
{
    case Claude = 'claude';
    case OpenAi = 'openai';

    public function getLabel(): string
    {
        return $this->label();
    }

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
