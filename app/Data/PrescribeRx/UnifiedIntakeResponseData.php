<?php

namespace App\Data\PrescribeRx;

use Spatie\LaravelData\Data;

/**
 * Maps to the `data` payload returned by `POST /telehealth/intake/unified`
 * (HTTP 201). The `workflow` block reports which downstream side-effects
 * succeeded so the caller can log partial-success states.
 */
class UnifiedIntakeResponseData extends Data
{
    public function __construct(
        public string $encounter_id,
        public string $encounter_number,
        public string $patient_chart_id,
        public string $patient_number,
        public ?string $user_id = null,
        public string $status = 'pending_intake',
        public ?string $status_label = null,
        public ?string $encounter_type = null,
        public ?int $completeness_score = null,
        public mixed $preclusions = null,
        /** @var array<string, mixed> */
        public array $workflow = [],
    ) {}
}
