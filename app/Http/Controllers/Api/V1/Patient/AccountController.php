<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Actions\Patient\LinkPatientToPrxChartAction;
use App\Data\Patient\PatientResource;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A patient's own account, as distinct from the clinical data PortalController
 * proxies. Nothing here talks to the provider except to verify a claim.
 */
class AccountController extends ApiController
{
    /**
     * Claim the medical record created by one of this patient's orders.
     *
     * Until an account is linked to a PRX chart the portal has nothing to show
     * it — `IssuePortalTokenAction` refuses to mint without one — so this is
     * the step between "I have a login" and "I can see my care".
     *
     * The lead uuid is NOT proof of anything on its own — `POST /leads` is
     * anonymous and hands back the uuid, so anyone can mint one for any
     * address. `LinkPatientToPrxChartAction` takes the chart from the order's
     * ENCOUNTER, a row only our own server or a signed webhook can write, and
     * ignores the copy on the lead entirely.
     *
     * @tags PatientAuth
     */
    public function linkChart(Request $request, LinkPatientToPrxChartAction $action): JsonResponse
    {
        $validated = $request->validate([
            'lead_uuid' => ['required', 'string', 'uuid'],
        ]);

        $lead = Lead::where('uuid', $validated['lead_uuid'])->first();

        if ($lead === null) {
            // Deliberately the same refusal the action gives for a lead that
            // belongs to someone else. Whether a uuid exists is not something a
            // caller should be able to enumerate.
            throw ValidationException::withMessages([
                'lead_uuid' => 'That order does not belong to this account.',
            ]);
        }

        $patient = $action->execute($request->user(), $lead);

        return $this->success(['patient' => PatientResource::fromModel($patient)->toArray()]);
    }
}
