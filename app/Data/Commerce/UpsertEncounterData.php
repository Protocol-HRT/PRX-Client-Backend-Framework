<?php

namespace App\Data\Commerce;

use App\Enums\EncounterStatus;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * Webhook-friendly DTO. Mirrors the shape of fields PRX sends. No clinical
 * fields permitted — the handler that builds this from the webhook payload
 * is responsible for filtering them out.
 */
class UpsertEncounterData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        #[Required, Max(64)]
        public string $prescribe_rx_encounter_id,
        #[WithCast(EnumCast::class)]
        public EncounterStatus $status = EncounterStatus::Pending,
        public ?string $lead_uuid = null,
        #[Max(64)]
        public ?string $prescribe_rx_patient_id = null,
        #[Max(64)]
        public ?string $prescribe_rx_encounter_type_id = null,
        public ?float $total_amount = null,
        public bool $is_sandbox = false,
        public ?string $submitted_at = null,
        public ?string $reviewed_at = null,
        public ?string $completed_at = null,
        public ?string $cancelled_at = null,
        public ?array $metadata = null,
    ) {}
}
