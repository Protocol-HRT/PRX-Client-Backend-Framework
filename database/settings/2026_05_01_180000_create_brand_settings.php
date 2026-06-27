<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('brand.name', '');
        $this->migrator->add('brand.tagline', '');
        $this->migrator->add('brand.logo_path', '');
        $this->migrator->add('brand.favicon_path', '');
        $this->migrator->add('brand.hero_image_path', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('brand.name');
        $this->migrator->deleteIfExists('brand.tagline');
        $this->migrator->deleteIfExists('brand.logo_path');
        $this->migrator->deleteIfExists('brand.favicon_path');
        $this->migrator->deleteIfExists('brand.hero_image_path');
    }
};
