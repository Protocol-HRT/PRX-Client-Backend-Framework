<?php

namespace App\Actions\Commerce;

use App\Actions\Concerns\Transacts;
use App\Data\Commerce\UpsertEncounterData;
use App\Models\Commerce\Encounter;
use App\Models\Lead;

/**
 * Idempotent — looks up by prescribe_rx_encounter_id and either creates or
 * updates. Webhook delivery is at-least-once; double-fires must not duplicate.
 */
class UpsertEncounterAction
{
    use Transacts;

    public function execute(UpsertEncounterData $data): Encounter
    {
        return $this->tx(function () use ($data) {
            $lead = $data->lead_uuid
                ? Lead::query()->where('uuid', $data->lead_uuid)->first()
                : null;

            return Encounter::query()->updateOrCreate(
                ['prescribe_rx_encounter_id' => $data->prescribe_rx_encounter_id],
                [
                    'lead_id' => $lead?->id,
                    'prescribe_rx_patient_id' => $data->prescribe_rx_patient_id,
                    'prescribe_rx_encounter_type_id' => $data->prescribe_rx_encounter_type_id,
                    'status' => $data->status,
                    'submitted_at' => $data->submitted_at,
                    'reviewed_at' => $data->reviewed_at,
                    'completed_at' => $data->completed_at,
                    'cancelled_at' => $data->cancelled_at,
                    'total_amount' => $data->total_amount,
                    'is_sandbox' => $data->is_sandbox,
                    'metadata' => $data->metadata,
                ],
            );
        });
    }
}
