<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integrations.prescribe_rx_enabled', false);
        $this->migrator->add('integrations.prescribe_rx_environment', 'sandbox');
        $this->migrator->addEncrypted('integrations.prescribe_rx_api_token', null);
        $this->migrator->add('integrations.prescribe_rx_sales_org_id', null);
        $this->migrator->add('integrations.prescribe_rx_client_id', null);
    }

    public function down(): void
    {
        foreach ([
            'prescribe_rx_enabled',
            'prescribe_rx_environment',
            'prescribe_rx_api_token',
            'prescribe_rx_sales_org_id',
            'prescribe_rx_client_id',
        ] as $key) {
            $this->migrator->deleteIfExists("integrations.{$key}");
        }
    }
};
