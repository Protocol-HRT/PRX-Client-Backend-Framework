<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->migrator->encrypt('llm.claude_api_key');
            $this->migrator->encrypt('llm.openai_api_key');
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->migrator->decrypt('llm.claude_api_key');
            $this->migrator->decrypt('llm.openai_api_key');
        });
    }
};
