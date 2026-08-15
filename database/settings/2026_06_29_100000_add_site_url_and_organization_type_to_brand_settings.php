<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('brand.site_url', null);
        $this->migrator->add('brand.organization_type', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('brand.site_url');
        $this->migrator->deleteIfExists('brand.organization_type');
    }
};
