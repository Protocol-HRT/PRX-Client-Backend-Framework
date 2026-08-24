<?php

namespace App\Http\Controllers\PrescribeRx;

use App\Actions\Leads\MarkLeadHandedOffAction;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Advisory ping fired by the prescribe-rx embed's onComplete callback.
 *
 * NOT authoritative — the data here is client-supplied and unverified.
 * Treat as a "the user appears to have submitted" hint useful for snappy
 * UI updates. The signed webhook (POST /api/webhooks/prescribe-rx) is the
 * source of truth for status, encounter creation, and order/fulfillment.
 *
 * Why we still want this endpoint: webhooks can take seconds-to-minutes
 * after submission depending on PRX queue state; this lets us flip the
 * lead from `new` → `handed_off` immediately on the front-end so the
 * "thank you" page knows what to render.
 */
class EmbedCompleteController
{
    public function __invoke(Request $request, MarkLeadHandedOffAction $action): JsonResponse
    {
        $data = $request->validate([
            'lead_uuid' => ['required', 'string', 'uuid'],
            'encounter_id' => ['nullable', 'string', 'max:64'],
            'patient_id' => ['nullable', 'string', 'max:64'],
        ]);

        $lead = Lead::query()->where('uuid', $data['lead_uuid'])->first();
        if (! $lead) {
            // Fail open — return success so the front-end UX isn't blocked
            // by a missing lead, but log it so we can investigate.
            Log::warning('embed-complete ping for unknown lead', $data);

            return response()->json(['ok' => true]);
        }

        $action->execute(
            $lead,
            $data['encounter_id'] ?? null,
            $data['patient_id'] ?? null,
        );

        return response()->json(['ok' => true]);
    }
}
