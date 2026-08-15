<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('llm.active_provider', null);
        $this->migrator->add('llm.claude_api_key', null);
        $this->migrator->add('llm.claude_model', 'claude-sonnet-4-6');
        $this->migrator->add('llm.openai_api_key', null);
        $this->migrator->add('llm.openai_model', 'gpt-4o-mini');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('llm.active_provider');
        $this->migrator->deleteIfExists('llm.claude_api_key');
        $this->migrator->deleteIfExists('llm.claude_model');
        $this->migrator->deleteIfExists('llm.openai_api_key');
        $this->migrator->deleteIfExists('llm.openai_model');
    }
};
