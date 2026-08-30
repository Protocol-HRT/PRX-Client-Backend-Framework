<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The row behind `IntegrationSettings::$prescribe_rx_encounter_type_id`.
 *
 * The property, its form field and its use in checkout all shipped without
 * this, and the failure is a nasty shape: the settings mapper backfills a
 * missing property from the class default when LOADING, so the page opened
 * normally and looked fine, then refused every save with
 *
 *   Tried saving settings 'App\Settings\IntegrationSettings', and the
 *   following properties were missing: prescribe_rx_encounter_type_id
 *
 * — which reads like a form problem and is actually a missing database row.
 * Nothing was wrong with what the operator typed, and the whole page was
 * unsaveable, not just that field.
 *
 * Null default on purpose: it is a fallback, and a guessed encounter type is
 * worse than none. What it means is a live question — the catalog cascade
 * (`provider_encounter_type_id` on product/category/package) and this global
 * value are currently two different answers to "which encounter type does this
 * cart get". See docs/prescribe-rx/dev.md.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integrations.prescribe_rx_encounter_type_id', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('integrations.prescribe_rx_encounter_type_id');
    }
};
