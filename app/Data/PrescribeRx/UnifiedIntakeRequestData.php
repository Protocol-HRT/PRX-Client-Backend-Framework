<?php

namespace App\Data\PrescribeRx;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Single-call payload for `POST /telehealth/intake/unified` —
 * creates patient + encounter + intake answers atomically on the
 * prescribe-rx side.
 *
 * One of `encounter_type_id` / `encounter_type_slug` /
 * `encounter_type_name` MUST be set (priority: id > slug > name).
 */
class UnifiedIntakeRequestData extends Data
{
    public function __construct(
        #[Required]
        public PatientData $patient,
        public ?string $encounter_type_id = null,
        public ?string $encounter_type_slug = null,
        public ?string $encounter_type_name = null,
        public ?string $client_id = null,
        public ?string $client_number = null,
        public ?string $sales_org_id = null,
        public ?string $sales_org_number = null,
        public ?VitalsData $vitals = null,
        public ?MedicalHistoryData $medical_history = null,
        /**
         * Keyed by field slug from
         * `GET /telehealth/encounter-types/{id}/schema`.
         *
         * @var array<string, mixed>
         */
        public array $answers = [],
        /** @var array<int, string> */
        public array $product_ids = [],
        public ?string $reason_for_visit = null,
        /** @var DataCollection<int, ConsentData>|null */
        public ?DataCollection $consents = null,
    ) {}
}
