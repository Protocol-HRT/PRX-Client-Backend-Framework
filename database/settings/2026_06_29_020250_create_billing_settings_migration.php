<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('billing.checkout_path', 'prx');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('billing.checkout_path');
    }
};
