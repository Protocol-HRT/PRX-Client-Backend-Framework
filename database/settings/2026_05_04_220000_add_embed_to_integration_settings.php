<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * prescribe-rx embed integration: the embed code (per encounter type, generated
 * in the prescribe-rx admin) acts as both auth + config for the iframe widget.
 * Webhook secret is the HMAC key prescribe-rx signs delivery payloads with.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->addEncrypted('integrations.prescribe_rx_embed_code', null);
        $this->migrator->addEncrypted('integrations.prescribe_rx_webhook_secret', null);
    }

    public function down(): void
    {
        foreach (['prescribe_rx_embed_code', 'prescribe_rx_webhook_secret'] as $key) {
            $this->migrator->deleteIfExists("integrations.{$key}");
        }
    }
};
