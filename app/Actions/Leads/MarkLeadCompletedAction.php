<?php

namespace App\Actions\Leads;

use App\Actions\Concerns\Transacts;
use App\Enums\LeadStatus;
use App\Models\Lead;

class MarkLeadCompletedAction
{
    use Transacts;

    /**
     * @param  array<string, mixed>|null  $response
     */
    public function execute(Lead $lead, ?array $response = null): Lead
    {
        return $this->tx(function () use ($lead, $response) {
            $lead->update([
                'status' => LeadStatus::Completed,
                'completed_at' => now(),
                'prescribe_rx_response' => $response,
            ]);

            return $lead->fresh();
        });
    }
}
