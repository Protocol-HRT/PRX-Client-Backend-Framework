<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Actions\Patient\IssuePortalTokenAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Patient;
use App\Services\PrescribeRx\Client;
use App\Services\PrescribeRx\Exceptions\PrescribeRxException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Patient portal — proxies PRX /me/patient/* endpoints.
 *
 * Patient-token calls go through withPatientToken() which handles automatic
 * 401 recovery (evict cache → re-mint → retry once). Scheduling endpoints
 * use the sales-org token directly and bypass the patient-token flow.
 *
 * All routes require `auth:sanctum` + `patient` middleware (set in api.php).
 */
class PortalController extends ApiController
{
    public function __construct(
        private readonly Client $prx,
        private readonly IssuePortalTokenAction $tokenAction,
    ) {}

    /**
     * Patient portal dashboard summary.
     *
     * @tags Patient Portal
     */
    public function dashboard(Request $request): JsonResponse
    {
        $patient = $request->user();

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->getPatientDashboard($t))
        );
    }

    /**
     * List the patient's encounters.
     *
     * @tags Patient Portal
     */
    public function encounters(Request $request): JsonResponse
    {
        $patient = $request->user();
        $query = $request->only(['page', 'per_page', 'status']);

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->getPatientEncounters($t, $query))
        );
    }

    /**
     * Retrieve a video-room token for an encounter.
     *
     * PRX requires the patient token (not the sales-org token) for video room
     * access so the PHI audit log attributes the session to the patient.
     *
     * @tags Patient Portal
     */
    public function videoToken(Request $request, string $encounterId): JsonResponse
    {
        $patient = $request->user();

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->getEncounterVideoToken($t, $encounterId))
        );
    }

    /**
     * List the patient's vitals.
     *
     * @tags Patient Portal
     */
    public function vitals(Request $request): JsonResponse
    {
        $patient = $request->user();

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->getPatientVitals($t))
        );
    }

    /**
     * Record a new vital entry.
     *
     * @tags Patient Portal
     */
    public function storeVital(Request $request): JsonResponse
    {
        $patient = $request->user();
        $data = $request->all();

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->recordPatientVital($t, $data)),
            status: 201
        );
    }

    /**
     * List the patient's orders.
     *
     * @tags Patient Portal
     */
    public function orders(Request $request): JsonResponse
    {
        $patient = $request->user();

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->getPatientOrders($t))
        );
    }

    /**
     * List the patient's prescriptions.
     *
     * @tags Patient Portal
     */
    public function prescriptions(Request $request): JsonResponse
    {
        $patient = $request->user();

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->getPatientPrescriptions($t))
        );
    }

    /**
     * List the patient's conversations.
     *
     * @tags Patient Portal
     */
    public function conversations(Request $request): JsonResponse
    {
        $patient = $request->user();

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->getPatientConversations($t))
        );
    }

    /**
     * List messages in a conversation.
     *
     * @tags Patient Portal
     */
    public function conversationMessages(Request $request, string $conversationId): JsonResponse
    {
        $patient = $request->user();

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->getConversationMessages($t, $conversationId))
        );
    }

    /**
     * Send a message in a conversation.
     *
     * @tags Patient Portal
     */
    public function sendMessage(Request $request, string $conversationId): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $patient = $request->user();
        $body = $validated['body'];

        return $this->success(
            $this->withPatientToken($patient, fn ($t) => $this->prx->sendConversationMessage($t, $conversationId, $body)),
            status: 201
        );
    }

    /**
     * List available scheduling slots.
     *
     * Scheduling uses the sales-org token (not the patient token).
     *
     * @tags Patient Portal
     */
    public function availabilitySlots(Request $request): JsonResponse
    {
        return $this->success(
            $this->prx->getAvailabilitySlots($request->only(['start_date', 'end_date', 'encounter_type_id']))
        );
    }

    /**
     * Book an appointment.
     *
     * Injects the patient's PRX chart ID. Scheduling uses the sales-org token.
     *
     * @tags Patient Portal
     */
    public function bookAppointment(Request $request): JsonResponse
    {
        /** @var Patient $patient */
        $patient = $request->user();

        $payload = array_merge($request->except('_token'), [
            'patient_chart_id' => $patient->prx_patient_chart_id,
        ]);

        return $this->success($this->prx->createAppointment($payload), status: 201);
    }

    /**
     * Run a PRX call that requires a patient token, with automatic 401 recovery.
     *
     * On 401: evicts the cached token, re-mints via issue-token, retries once.
     * If the re-mint call itself fails (chart deleted / org access revoked), propagates.
     *
     * @param  callable(string $token): mixed  $call
     */
    private function withPatientToken(Patient $patient, callable $call): mixed
    {
        try {
            return $call($this->tokenAction->execute($patient));
        } catch (PrescribeRxException $e) {
            if ($e->httpStatus !== 401) {
                throw $e;
            }

            return $call($this->tokenAction->renew($patient));
        }
    }
}
