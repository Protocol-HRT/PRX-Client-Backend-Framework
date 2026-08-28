<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Email provider selection, so the sending transport is an operational choice
 * rather than a deployment one.
 *
 * Everything defaults to null / false, which means "keep using .env" and
 * "send nothing". That is deliberate: an install upgrading into this must not
 * have its mailer silently repointed, and must not start sending because a
 * settings row appeared.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('communication.mail_provider', null);
        $this->migrator->addEncrypted('communication.mailgun_secret', null);
        $this->migrator->add('communication.mailgun_domain', null);
        $this->migrator->add('communication.mailgun_endpoint', null);
        $this->migrator->addEncrypted('communication.postmark_token', null);
        $this->migrator->addEncrypted('communication.ses_key', null);
        $this->migrator->addEncrypted('communication.ses_secret', null);
        $this->migrator->add('communication.ses_region', null);
        $this->migrator->add('communication.mail_from_address', null);
        $this->migrator->add('communication.mail_from_name', null);
        $this->migrator->add('communication.email_enabled', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('communication.mail_provider');
        $this->migrator->deleteIfExists('communication.mailgun_secret');
        $this->migrator->deleteIfExists('communication.mailgun_domain');
        $this->migrator->deleteIfExists('communication.mailgun_endpoint');
        $this->migrator->deleteIfExists('communication.postmark_token');
        $this->migrator->deleteIfExists('communication.ses_key');
        $this->migrator->deleteIfExists('communication.ses_secret');
        $this->migrator->deleteIfExists('communication.ses_region');
        $this->migrator->deleteIfExists('communication.mail_from_address');
        $this->migrator->deleteIfExists('communication.mail_from_name');
        $this->migrator->deleteIfExists('communication.email_enabled');
    }
};
