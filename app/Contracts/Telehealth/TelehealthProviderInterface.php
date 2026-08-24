<?php

namespace App\Contracts\Telehealth;

use App\Data\PrescribeRx\PatientData;
use App\Models\Lead;

/**
 * Provider-agnostic interface for telehealth / pharmacy fulfillment backends.
 *
 * All methods return raw arrays — each concrete implementation handles its own
 * internal DTO mapping. The service layer above normalises these arrays into our
 * own spatie/laravel-data DTOs as needed, keeping the interface itself clean and
 * free from provider-specific shapes.
 *
 * Return type conventions:
 *   - Read operations return `array` (empty array if the remote returns no rows).
 *   - Write operations return `array` (the created / updated resource).
 *   - Void operations (cancel, delete) return nothing and throw on failure.
 *   - Nullable returns (`?array`, `?string`) indicate an optional / unsupported result.
 *
 * All implementations MUST throw on unrecoverable errors (network failure,
 * authentication error, validation rejection). The Action layer translates those
 * into user-facing toasts.
 */
interface TelehealthProviderInterface
{
    // ─── Provider metadata ────────────────────────────────────────────────

    /** Human-readable provider name, e.g. "PrescribeRx". */
    public function getName(): string;

    /** URL-safe slug for config keys / DB columns, e.g. "prescribe-rx". */
    public function getSlug(): string;

    // ─── Capability flags ─────────────────────────────────────────────────

    /**
     * Whether this provider supports an embed (iframe / SDK) checkout flow.
     * When false, `buildEmbedPayload()` returns null.
     */
    public function supportsEmbed(): bool;

    /**
     * Whether this provider can issue patient-scoped auth tokens so patients
     * can access a hosted portal directly without a new login.
     * When false, `issuePatientToken()` returns null.
     */
    public function supportsPatientPortalAuth(): bool;

    // ─── Catalog ──────────────────────────────────────────────────────────

    /**
     * Full catalog: products and packages available for ordering.
     *
     * @return array<string, mixed>
     */
    public function getCatalog(): array;

    /**
     * List active encounter types available for intake.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listEncounterTypes(): array;

    /**
     * Return the full intake schema for one encounter type.
     * Drives the dynamic wizard step / question rendering.
     *
     * @return array<string, mixed>
     */
    public function getEncounterTypeSchema(string $encounterTypeId): array;

    // ─── Patient lifecycle ────────────────────────────────────────────────

    /**
     * Create a new patient record in the provider.
     *
     * @return array<string, mixed>
     */
    public function createPatient(PatientData $data): array;

    /**
     * Fetch a patient by their provider-issued patient ID.
     *
     * @return array<string, mixed>
     */
    public function getPatient(string $providerPatientId): array;

    /**
     * Update demographic or contact details for an existing patient.
     *
     * @return array<string, mixed>
     */
    public function updatePatient(string $providerPatientId, PatientData $data): array;

    /**
     * Look up a patient by email address. Returns null when no match exists.
     *
     * @return array<string, mixed>|null
     */
    public function findPatientByEmail(string $email): ?array;

    // ─── Patient chart ────────────────────────────────────────────────────

    /**
     * Retrieve the clinical chart for a patient.
     *
     * @param  array<int, string>  $include  Related resources to sideload.
     * @return array<string, mixed>
     */
    public function getPatientChart(string $providerPatientId, array $include = []): array;

    /**
     * Record a vital sign measurement on the patient's chart.
     *
     * @param  array<string, mixed>  $vital
     * @return array<string, mixed>
     */
    public function addPatientVital(string $providerPatientId, array $vital): array;

    /**
     * Add an allergy entry to the patient's chart.
     *
     * @param  array<string, mixed>  $allergy
     * @return array<string, mixed>
     */
    public function addPatientAllergy(string $providerPatientId, array $allergy): array;

    /**
     * Add a current medication to the patient's chart.
     *
     * @param  array<string, mixed>  $medication
     * @return array<string, mixed>
     */
    public function addPatientMedication(string $providerPatientId, array $medication): array;

    /**
     * Add a diagnosis / condition to the patient's chart.
     *
     * @param  array<string, mixed>  $condition
     * @return array<string, mixed>
     */
    public function addPatientCondition(string $providerPatientId, array $condition): array;

    // ─── Intake / Encounters ──────────────────────────────────────────────

    /**
     * Submit a unified intake: creates patient + encounter + answers in one call.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function submitIntake(array $payload, ?string $idempotencyKey = null): array;

    /**
     * Evaluate whether a patient is pre-excluded (has contradictions) for a
     * given encounter type before completing the full intake form.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function evaluatePreclusions(string $encounterTypeId, array $answers, ?string $patientChartId = null): array;

    /**
     * Retrieve a single encounter with optional related resources sideloaded.
     *
     * @param  array<int, string>  $include
     * @return array<string, mixed>
     */
    public function getEncounter(string $encounterId, array $include = []): array;

    /**
     * Lightweight status check for an encounter (avoids fetching the full record).
     *
     * @return array<string, mixed>
     */
    public function getEncounterStatus(string $encounterId): array;

    /**
     * Submit amended intake answers for a previously submitted encounter.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function amendEncounterAnswers(string $encounterId, array $answers): array;

    /**
     * List encounters for a patient, optionally filtered.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listPatientEncounters(string $providerPatientId, array $filters = []): array;

    // ─── Orders ───────────────────────────────────────────────────────────

    /**
     * Retrieve a single order with optional related resources sideloaded.
     *
     * @param  array<int, string>  $include
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId, array $include = []): array;

    /**
     * List orders for a patient, optionally filtered.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listPatientOrders(string $providerPatientId, array $filters = []): array;

    /**
     * Preview the calculated totals (subtotal, tax, shipping, discount) for a
     * set of cart lines before checkout confirmation.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>|null  $shippingAddress
     * @return array<string, mixed>
     */
    public function previewCart(array $lines, ?string $couponCode = null, ?array $shippingAddress = null): array;

    /**
     * Validate a coupon code against a set of cart lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    public function validateCoupon(string $code, array $lines = []): array;

    // ─── Prescriptions ────────────────────────────────────────────────────

    /**
     * List prescriptions issued to a patient.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listPatientPrescriptions(string $providerPatientId, array $filters = []): array;

    // ─── Subscriptions ────────────────────────────────────────────────────

    /**
     * List active and past subscriptions for a patient.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPatientSubscriptions(string $providerPatientId): array;

    /**
     * Create a new subscription.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createSubscription(array $data): array;

    /**
     * Update subscription details (plan, billing cycle, etc.).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateSubscription(string $subscriptionId, array $data): array;

    /** Cancel a subscription immediately or at the end of the billing period. */
    public function cancelSubscription(string $subscriptionId): void;

    // ─── Lab orders ───────────────────────────────────────────────────────

    /**
     * Create a lab order for a patient.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createLabOrder(array $data): array;

    /**
     * List lab orders placed for a patient.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPatientLabOrders(string $providerPatientId): array;

    /**
     * Return the available lab test catalog.
     *
     * @return array<string, mixed>
     */
    public function getLabCatalog(): array;

    // ─── Scheduling ───────────────────────────────────────────────────────

    /**
     * Return available appointment slots for a provider within a date range.
     *
     * @return array<string, mixed>
     */
    public function getProviderAvailability(string $providerId, string $startDate, string $endDate): array;

    /**
     * Book an appointment slot.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function bookAppointment(array $data): array;

    /**
     * List booked appointments for a patient.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPatientAppointments(string $providerPatientId): array;

    // ─── Webhooks ─────────────────────────────────────────────────────────

    /**
     * Register a webhook subscription for a specific event type.
     *
     * @return array<string, mixed>
     */
    public function registerWebhook(string $eventType, string $endpointUrl): array;

    /**
     * List all registered webhook subscriptions for this tenant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWebhooks(): array;

    /** Delete (unsubscribe) a webhook by its provider-issued ID. */
    public function deleteWebhook(string $webhookId): void;

    // ─── Embed / patient portal ───────────────────────────────────────────

    /**
     * Build the payload the frontend passes to the provider's embed SDK.
     * Returns null when `supportsEmbed()` is false.
     *
     * @return array<string, mixed>|null
     */
    public function buildEmbedPayload(Lead $lead): ?array;

    /**
     * Issue a short-lived patient-scoped auth token so the patient can access
     * the provider's hosted portal without a separate login.
     * Returns null when `supportsPatientPortalAuth()` is false.
     */
    public function issuePatientToken(string $providerPatientId): ?string;
}
