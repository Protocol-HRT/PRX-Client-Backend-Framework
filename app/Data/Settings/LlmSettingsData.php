<?php

namespace App\Data\Settings;

use App\Enums\Llm\LlmProvider;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class LlmSettingsData extends Data
{
    public function __construct(
        public ?LlmProvider $active_provider = null,
        #[Max(500)]
        public ?string $claude_api_key = null,
        #[Max(200)]
        public string $claude_model = 'claude-sonnet-4-6',
        #[Max(500)]
        public ?string $openai_api_key = null,
        #[Max(200)]
        public string $openai_model = 'gpt-4o-mini',
    ) {}
}
