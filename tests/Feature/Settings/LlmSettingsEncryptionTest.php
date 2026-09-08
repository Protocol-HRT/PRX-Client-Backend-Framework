<?php

namespace Tests\Feature\Settings;

use App\Settings\LlmSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LlmSettingsEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public static function credentials(): array
    {
        return [
            'configured keys' => ['test-claude-secret', 'test-openai-secret'],
            'empty keys' => ['', ''],
            'unset keys' => [null, null],
        ];
    }

    #[DataProvider('credentials')]
    public function test_existing_plaintext_keys_migrate_and_round_trip(?string $claude, ?string $openai): void
    {
        $values = ['claude_api_key' => $claude, 'openai_api_key' => $openai];
        foreach ($values as $name => $value) {
            DB::table('settings')->where('group', 'llm')->where('name', $name)
                ->update(['payload' => json_encode($value)]);
        }

        $migration = require database_path('settings/2026_09_06_232645_encrypt_llm_api_keys.php');
        $migration->up();

        foreach ($values as $name => $value) {
            $stored = json_decode(DB::table('settings')->where('group', 'llm')->where('name', $name)->value('payload'), true);
            if ($value === null) {
                $this->assertNull($stored);
            } else {
                $this->assertNotSame($value, $stored);
                $this->assertSame($value, decrypt($stored));
            }
        }

        $settings = app(LlmSettings::class)->refresh();
        $this->assertSame($claude, $settings->claude_api_key);
        $this->assertSame($openai, $settings->openai_api_key);

        $migration->down();
        foreach ($values as $name => $value) {
            $stored = json_decode(DB::table('settings')->where('group', 'llm')->where('name', $name)->value('payload'), true);
            $this->assertSame($value, $stored);
        }
    }

    public function test_future_saves_encrypt_both_provider_keys(): void
    {
        $settings = app(LlmSettings::class);
        $settings->claude_api_key = 'new-claude-secret';
        $settings->openai_api_key = 'new-openai-secret';
        $settings->save();

        foreach (['claude_api_key' => 'new-claude-secret', 'openai_api_key' => 'new-openai-secret'] as $name => $value) {
            $stored = json_decode(DB::table('settings')->where('group', 'llm')->where('name', $name)->value('payload'), true);
            $this->assertNotSame($value, $stored);
            $this->assertSame($value, decrypt($stored));
        }

        $settings->refresh();
        $this->assertSame('new-claude-secret', $settings->claude_api_key);
        $this->assertSame('new-openai-secret', $settings->openai_api_key);
    }
}
