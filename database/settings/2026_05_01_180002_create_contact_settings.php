<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('contact.support_email', null);
        $this->migrator->add('contact.sales_email', null);
        $this->migrator->add('contact.phone', null);
        $this->migrator->add('contact.address_line_1', null);
        $this->migrator->add('contact.address_line_2', null);
        $this->migrator->add('contact.city', null);
        $this->migrator->add('contact.state', null);
        $this->migrator->add('contact.postal_code', null);
        $this->migrator->add('contact.country', 'US');
        $this->migrator->add('contact.business_hours', null);
        $this->migrator->add('contact.instagram_url', null);
        $this->migrator->add('contact.twitter_url', null);
        $this->migrator->add('contact.facebook_url', null);
        $this->migrator->add('contact.linkedin_url', null);
        $this->migrator->add('contact.tiktok_url', null);
        $this->migrator->add('contact.youtube_url', null);
    }

    public function down(): void
    {
        foreach ([
            'support_email', 'sales_email', 'phone',
            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
            'business_hours',
            'instagram_url', 'twitter_url', 'facebook_url', 'linkedin_url', 'tiktok_url', 'youtube_url',
        ] as $key) {
            $this->migrator->deleteIfExists("contact.{$key}");
        }
    }
};
