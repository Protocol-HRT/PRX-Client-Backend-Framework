<?php

namespace App\Services\Llm;

use App\Data\Llm\GeneratedSeoData;
use App\Enums\Llm\LlmProvider;
use App\Settings\LlmSettings;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SeoMetaGenerator
{
    public function __construct(private readonly LlmSettings $settings) {}

    public function generateForModel(Model $model): GeneratedSeoData
    {
        $client = $this->resolveClient();
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($model);

        $raw = $client->complete($systemPrompt, $userPrompt, 600);

        return $this->parseResponse($raw);
    }

    private function resolveClient(): ClaudeClient|OpenAiClient
    {
        $provider = LlmProvider::tryFrom($this->settings->active_provider ?? '');

        return match ($provider) {
            LlmProvider::Claude => $this->makeClaudeClient(),
            LlmProvider::OpenAi => $this->makeOpenAiClient(),
            default => throw new RuntimeException('No LLM provider configured. Set one in Settings → AI / LLM.'),
        };
    }

    private function makeClaudeClient(): ClaudeClient
    {
        if (empty($this->settings->claude_api_key)) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        return new ClaudeClient($this->settings->claude_api_key, $this->settings->claude_model);
    }

    private function makeOpenAiClient(): OpenAiClient
    {
        if (empty($this->settings->openai_api_key)) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        return new OpenAiClient($this->settings->openai_api_key, $this->settings->openai_model);
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an SEO metadata expert specializing in health and wellness e-commerce.
Generate a compelling meta_title (max 60 characters) and meta_description (max 160 characters)
for the given content. The title should be click-worthy and keyword-rich. The description should
summarize the value proposition clearly. Respond with valid JSON only, no markdown, no explanation.
Format: {"meta_title": "...", "meta_description": "..."}
PROMPT;
    }

    private function buildUserPrompt(Model $model): string
    {
        $parts = [];
        $parts[] = 'Content type: '.class_basename($model);

        foreach (['name', 'title', 'subtitle', 'short_description', 'description', 'excerpt'] as $field) {
            if (! empty($model->{$field})) {
                $label = ucfirst(str_replace('_', ' ', $field));
                $value = mb_substr((string) $model->{$field}, 0, 500);
                $parts[] = "{$label}: {$value}";
            }
        }

        if (method_exists($model, 'categories') && $model->relationLoaded('categories')) {
            $cats = $model->categories->pluck('name')->join(', ');
            if ($cats) {
                $parts[] = "Categories: {$cats}";
            }
        }

        return implode("\n", $parts);
    }

    private function parseResponse(string $raw): GeneratedSeoData
    {
        $decoded = json_decode(trim($raw), true);

        if (! is_array($decoded) || empty($decoded['meta_title']) || empty($decoded['meta_description'])) {
            throw new RuntimeException('LLM returned an unexpected response format.');
        }

        return new GeneratedSeoData(
            meta_title: mb_substr($decoded['meta_title'], 0, 60),
            meta_description: mb_substr($decoded['meta_description'], 0, 160),
        );
    }
}
