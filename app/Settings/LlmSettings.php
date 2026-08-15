<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class LlmSettings extends Settings
{
    /** Which provider powers the SEO generation feature. */
    public ?string $active_provider = null;

    /** Anthropic API key (encrypted at rest). */
    public ?string $claude_api_key = null;

    /** Claude model ID to use, e.g. claude-sonnet-4-6. */
    public string $claude_model = 'claude-sonnet-4-6';

    /** OpenAI API key (encrypted at rest). */
    public ?string $openai_api_key = null;

    /** OpenAI model ID to use, e.g. gpt-4o-mini. */
    public string $openai_model = 'gpt-4o-mini';

    public static function group(): string
    {
        return 'llm';
    }
}
