<?php

namespace App\Actions\Leads;

use App\Actions\Concerns\Transacts;
use App\Enums\LeadStatus;
use App\Models\Lead;

class MarkLeadHandedOffAction
{
    use Transacts;

    public function execute(Lead $lead, ?string $encounterId = null, ?string $patientId = null): Lead
    {
        return $this->tx(function () use ($lead, $encounterId, $patientId) {
            $lead->update([
                'status' => LeadStatus::HandedOff->value,
                'prescribe_rx_encounter_id' => $encounterId ?? $lead->prescribe_rx_encounter_id,
                'prescribe_rx_patient_id' => $patientId ?? $lead->prescribe_rx_patient_id,
                'handed_off_at' => now(),
            ]);

            return $lead->fresh();
        });
    }
}
