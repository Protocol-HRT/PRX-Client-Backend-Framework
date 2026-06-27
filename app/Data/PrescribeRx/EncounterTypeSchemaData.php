<?php

namespace App\Data\PrescribeRx;

use Spatie\LaravelData\Data;

/**
 * Full intake schema for one encounter type. Drives the dynamic intake
 * wizard on the public site — each `step` becomes a wizard step and each
 * `field` becomes a Livewire form field.
 *
 * The slug on each field is what the unified-intake `answers` payload
 * keys against. `required_slugs` is a flattened list of required field
 * slugs across all steps (handy for client-side validation).
 *
 * Fields are kept loosely typed (array) here — there's a wide surface
 * (validation rules, options, depends_on, has_preclusions, maps_to, etc.)
 * and locking it down too tightly forces a redeploy every time
 * prescribe-rx adds a field type. Cast via accessors at render time.
 */
class EncounterTypeSchemaData extends Data
{
    public function __construct(
        /** @var array<string, mixed> */
        public array $encounter_type,
        /** @var array<int, array<string, mixed>> */
        public array $steps = [],
        /** @var array<int, string> */
        public array $field_slugs = [],
        /** @var array<int, string> */
        public array $required_slugs = [],
        /** @var array<string, mixed> */
        public array $meta = [],
    ) {}
}
