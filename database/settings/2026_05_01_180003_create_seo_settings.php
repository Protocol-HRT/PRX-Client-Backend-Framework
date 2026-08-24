<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.default_meta_title', '');
        $this->migrator->add('seo.default_meta_description', '');
        $this->migrator->add('seo.og_image_path', null);
        $this->migrator->add('seo.google_analytics_id', null);
        $this->migrator->add('seo.google_tag_manager_id', null);
        $this->migrator->add('seo.facebook_pixel_id', null);
        $this->migrator->add('seo.allow_indexing', true);
    }

    public function down(): void
    {
        foreach (['default_meta_title', 'default_meta_description', 'og_image_path', 'google_analytics_id', 'google_tag_manager_id', 'facebook_pixel_id', 'allow_indexing'] as $key) {
            $this->migrator->deleteIfExists("seo.{$key}");
        }
    }
};
