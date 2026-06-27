<?php

namespace App\Services\PrescribeRx;

use App\Data\PrescribeRx\EncounterTypeData;
use App\Data\PrescribeRx\EncounterTypeSchemaData;
use App\Data\PrescribeRx\UnifiedIntakeRequestData;
use App\Data\PrescribeRx\UnifiedIntakeResponseData;
use App\Services\PrescribeRx\Exceptions\PrescribeRxException;
use App\Settings\IntegrationSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HTTP client for the prescribe-rx telehealth unified API.
 *
 * Reads connection details from:
 *   - `config/prescribe-rx.php` (sandbox/prod URLs, timeouts, retries)
 *   - `App\Settings\IntegrationSettings` (per-tenant: enabled flag,
 *      environment selection, encrypted API token, optional org IDs)
 *
 * All public methods return typed DTOs and throw `PrescribeRxException`
 * on any failure (not configured, connection failure, non-2xx, validation
 * error). Action-layer callers translate those into toasts.
 *
 * Stub mode: when `config('prescribe-rx.stub')` is true, returns canned
 * fixtures without hitting the network. Useful for local dev before a
 * sales-org token is issued.
 */
class Client
{
    public function __construct(
        protected IntegrationSettings $settings,
    ) {}

    // ─── Endpoints ────────────────────────────────────────────────────────

    /**
     * `GET /telehealth/encounter-types`
     *
     * Lists active encounter types available for intake.
     *
     * @return array<int, EncounterTypeData>
     */
    public function listEncounterTypes(?string $telehealthCompanyId = null): array
    {
        if (config('prescribe-rx.stub')) {
            return [
                EncounterTypeData::from([
                    'id' => '019ce396-46a1-73ab-87d6-c40310555401',
                    'name' => 'GLP-1 Screening',
                    'slug' => 'glp1-screening',
                    'description' => 'GLP-1 weight management screening',
                    'icon' => 'ti-stethoscope',
                    'product_class' => 'Weight Management',
                    'product_type' => 'GLP-1 Agonist',
                    'is_featured' => true,
                    'requires_labs' => false,
                    'interaction_type' => 'Asynchronous',
                ]),
            ];
        }

        $query = $telehealthCompanyId ? ['telehealth_company_id' => $telehealthCompanyId] : [];

        $payload = $this->extractData(
            $this->request()->get('/telehealth/encounter-types', $query)
        );

        return EncounterTypeData::collect($payload, 'array');
    }

    /**
     * `GET /telehealth/encounter-types/{id}/schema`
     *
     * Returns the full intake schema for one encounter type — drives the
     * dynamic wizard. Use the resulting `field_slugs` as the keys for
     * the `answers` payload on `submitUnifiedIntake()`.
     */
    public function getEncounterTypeSchema(string $encounterTypeId): EncounterTypeSchemaData
    {
        if (config('prescribe-rx.stub')) {
            return EncounterTypeSchemaData::from([
                'encounter_type' => [
                    'id' => $encounterTypeId,
                    'name' => 'GLP-1 Screening',
                    'slug' => 'glp1-screening',
                ],
                'steps' => [
                    [
                        'step_name' => 'Eligibility',
                        'step_description' => 'Quick screening',
                        'step_type' => 'screening',
                        'display_order' => 1,
                        'is_required' => true,
                        'fields' => [
                            [
                                'slug' => 'glp1_diabetes_mellitus',
                                'label' => 'Have you been diagnosed with Type 2 Diabetes?',
                                'field_type' => 'radio',
                                'is_required' => true,
                                'options' => [
                                    ['value' => 'Yes', 'label' => 'Yes'],
                                    ['value' => 'No', 'label' => 'No'],
                                ],
                            ],
                        ],
                    ],
                ],
                'field_slugs' => ['glp1_diabetes_mellitus'],
                'required_slugs' => ['glp1_diabetes_mellitus'],
                'meta' => ['total_steps' => 1, 'total_fields' => 1],
            ]);
        }

        $payload = $this->extractData(
            $this->request()->get("/telehealth/encounter-types/{$encounterTypeId}/schema")
        );

        return EncounterTypeSchemaData::from($payload);
    }

    /**
     * `POST /telehealth/intake/unified`
     *
     * Single call that creates patient + encounter + intake answers. The
     * input DTO carries everything; this method merges in the configured
     * `client_id` / `sales_org_id` from IntegrationSettings if the request
     * doesn't already specify them.
     */
    public function submitUnifiedIntake(UnifiedIntakeRequestData $data): UnifiedIntakeResponseData
    {
        $payload = $this->withConfiguredOrg($data->toArray());

        if (config('prescribe-rx.stub')) {
            return UnifiedIntakeResponseData::from([
                'encounter_id' => '019d5561-7b2b-7096-8d90-4ee49ef2ede8',
                'encounter_number' => 'ENC-STUB-1',
                'patient_chart_id' => '019d5561-7b24-7096-baa7-85485643b0a7',
                'patient_number' => 'PAT-STUB-1',
                'status' => 'pending_intake',
                'status_label' => 'Pending Patient Intake',
                'encounter_type' => $data->encounter_type_name ?? 'Stub Encounter',
                'completeness_score' => 80,
                'workflow' => [
                    'user_created' => true,
                    'patient_chart_created' => true,
                    'encounter_created' => true,
                    'intake_stored' => true,
                ],
            ]);
        }

        $response = $this->request()->post('/telehealth/intake/unified', $payload);
        $body = $this->extractData($response);

        return UnifiedIntakeResponseData::from($body);
    }

    // ─── Internals ────────────────────────────────────────────────────────

    /**
     * Build a configured PendingRequest. Throws PrescribeRxException if the
     * integration isn't enabled or no token is configured.
     */
    protected function request(): PendingRequest
    {
        if (! $this->settings->prescribe_rx_enabled || empty($this->settings->prescribe_rx_api_token)) {
            throw PrescribeRxException::notConfigured();
        }

        return Http::baseUrl($this->baseUrl())
            ->withToken($this->settings->prescribe_rx_api_token)
            ->withHeaders(config('prescribe-rx.default_headers'))
            ->connectTimeout((int) config('prescribe-rx.http.connect_timeout', 5))
            ->timeout((int) config('prescribe-rx.http.request_timeout', 30))
            ->retry(
                (int) config('prescribe-rx.http.retry_times', 2),
                (int) config('prescribe-rx.http.retry_sleep_ms', 200),
                throw: false,
            )
            ->acceptJson()
            ->asJson();
    }

    protected function baseUrl(): string
    {
        $env = $this->settings->prescribe_rx_environment === 'production' ? 'production' : 'sandbox';

        return rtrim((string) config("prescribe-rx.urls.{$env}"), '/');
    }

    /**
     * Merge the configured org IDs into a unified-intake payload if the
     * request didn't specify them. Drops nulls so the API uses its own
     * fallback (the token's authenticated org).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withConfiguredOrg(array $payload): array
    {
        if (empty($payload['client_id']) && filled($this->settings->prescribe_rx_client_id)) {
            $payload['client_id'] = $this->settings->prescribe_rx_client_id;
        }
        if (empty($payload['sales_org_id']) && filled($this->settings->prescribe_rx_sales_org_id)) {
            $payload['sales_org_id'] = $this->settings->prescribe_rx_sales_org_id;
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }

    /**
     * Validate a successful prescribe-rx response and return its `data`
     * envelope. Throws on any non-2xx with the API's error message.
     *
     * @return array<string, mixed>
     */
    protected function extractData(Response $response): array
    {
        if (! $response->successful()) {
            try {
                Log::warning('PrescribeRx non-2xx', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            } catch (Throwable) {
                // ignore log failures
            }
            throw PrescribeRxException::fromResponse($response);
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new PrescribeRxException('PrescribeRx returned a non-JSON body.', $response->status());
        }

        // Both list and detail endpoints wrap payload in `{ success, data, ... }`.
        // Some endpoints return `data` as object, some as array — return as-is.
        return $body['data'] ?? [];
    }
}
