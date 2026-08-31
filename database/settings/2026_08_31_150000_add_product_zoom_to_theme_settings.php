<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Default OFF, deliberately. The frontend only downloads its zoom
        // library while this is true, so defaulting it on would ship weight
        // to every visitor of every install for a feature nobody asked for.
        $this->migrator->add('theme.product_zoom_enabled', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('theme.product_zoom_enabled');
    }
};
