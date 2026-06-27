<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('brand.announcement_enabled', true);
        $this->migrator->add('brand.announcement_emphasis', 'Licensed in All 50 States');
        $this->migrator->add('brand.announcement_text', '· Physician-Reviewed Protocols · Your Physician. Your Protocol.');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('brand.announcement_enabled');
        $this->migrator->deleteIfExists('brand.announcement_emphasis');
        $this->migrator->deleteIfExists('brand.announcement_text');
    }
};
