<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('theme.text_classes', []);
    }

    public function down(): void
    {
        $this->migrator->delete('theme.text_classes');
    }
};
