<?php

namespace App\Data\PrescribeRx;

use Spatie\LaravelData\Data;

/**
 * Encounter type summary returned by `GET /telehealth/encounter-types`.
 * The full intake schema is fetched separately via
 * `GET /telehealth/encounter-types/{id}/schema`.
 */
class EncounterTypeData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description = null,
        public ?string $icon = null,
        public ?string $product_class = null,
        public ?string $product_type = null,
        public bool $is_featured = false,
        public bool $requires_labs = false,
        public ?string $interaction_type = null,
    ) {}
}
