<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('theme.primary_color', '#0d0d0d');
        $this->migrator->add('theme.accent_color', '#c19a4b');
        $this->migrator->add('theme.accent_secondary_color', '#10b981');
        $this->migrator->add('theme.background_color', '#fafafa');
        $this->migrator->add('theme.text_color', '#0d0d0d');
        $this->migrator->add('theme.font_display', 'Cormorant Garamond');
        $this->migrator->add('theme.font_body', 'DM Sans');
    }

    public function down(): void
    {
        foreach (['primary_color', 'accent_color', 'accent_secondary_color', 'background_color', 'text_color', 'font_display', 'font_body'] as $key) {
            $this->migrator->deleteIfExists("theme.{$key}");
        }
    }
};
