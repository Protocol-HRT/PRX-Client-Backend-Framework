<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.tiktok_pixel_id', null);
        $this->migrator->add('seo.custom_head_scripts', null);
        $this->migrator->add('seo.custom_body_scripts', null);
        $this->migrator->add('theme.custom_css', null);
        $this->migrator->add('theme.frontend_template', 'default');
    }

    public function down(): void
    {
        foreach (['tiktok_pixel_id', 'custom_head_scripts', 'custom_body_scripts'] as $key) {
            $this->migrator->deleteIfExists("seo.{$key}");
        }

        foreach (['custom_css', 'frontend_template'] as $key) {
            $this->migrator->deleteIfExists("theme.{$key}");
        }
    }
};
