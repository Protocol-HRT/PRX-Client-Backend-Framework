<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('billing.upsells_enabled', true);
        $this->migrator->add('billing.upsells_limit', 4);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('billing.upsells_enabled');
        $this->migrator->deleteIfExists('billing.upsells_limit');
    }
};
