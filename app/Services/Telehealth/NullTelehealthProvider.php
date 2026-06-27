<?php

namespace App\Services\Telehealth;

use App\Contracts\Telehealth\TelehealthProviderInterface;
use App\Data\PrescribeRx\PatientData;
use App\Models\Lead;

/**
 * No-op telehealth provider for use when no provider is configured.
 *
 * Read operations return empty arrays so catalog / encounter-type listing
 * degrades gracefully on a fresh install. Write / mutation operations throw
 * immediately so the error surfaces at the action layer rather than silently
 * passing through.
 *
 * Swap this out by enabling a real provider in Admin → Settings → Integrations.
 */
class NullTelehealthProvider implements TelehealthProviderInterface
{
    // ─── Provider metadata ────────────────────────────────────────────────

    public function getName(): string
    {
        return 'None';
    }

    public function getSlug(): string
    {
        return 'null';
    }

    // ─── Capability flags ─────────────────────────────────────────────────

    public function supportsEmbed(): bool
    {
        return false;
    }

    public function supportsPatientPortalAuth(): bool
    {
        return false;
    }

    // ─── Catalog ──────────────────────────────────────────────────────────

    public function getCatalog(): array
    {
        return [];
    }

    public function listEncounterTypes(): array
    {
        return [];
    }

    public function getEncounterTypeSchema(string $encounterTypeId): array
    {
        return [];
    }

    // ─── Patient lifecycle ────────────────────────────────────────────────

    public function createPatient(PatientData $data): array
    {
        throw $this->notConfigured();
    }

    public function getPatient(string $providerPatientId): array
    {
        throw $this->notConfigured();
    }

    public function updatePatient(string $providerPatientId, PatientData $data): array
    {
        throw $this->notConfigured();
    }

    public function findPatientByEmail(string $email): ?array
    {
        return null;
    }

    // ─── Patient chart ────────────────────────────────────────────────────

    public function getPatientChart(string $providerPatientId, array $include = []): array
    {
        throw $this->notConfigured();
    }

    public function addPatientVital(string $providerPatientId, array $vital): array
    {
        throw $this->notConfigured();
    }

    public function addPatientAllergy(string $providerPatientId, array $allergy): array
    {
        throw $this->notConfigured();
    }

    public function addPatientMedication(string $providerPatientId, array $medication): array
    {
        throw $this->notConfigured();
    }

    public function addPatientCondition(string $providerPatientId, array $condition): array
    {
        throw $this->notConfigured();
    }

    // ─── Intake / Encounters ──────────────────────────────────────────────

    public function submitIntake(array $payload, ?string $idempotencyKey = null): array
    {
        throw $this->notConfigured();
    }

    public function evaluatePreclusions(string $encounterTypeId, array $answers, ?string $patientChartId = null): array
    {
        throw $this->notConfigured();
    }

    public function getEncounter(string $encounterId, array $include = []): array
    {
        throw $this->notConfigured();
    }

    public function getEncounterStatus(string $encounterId): array
    {
        throw $this->notConfigured();
    }

    public function amendEncounterAnswers(string $encounterId, array $answers): array
    {
        throw $this->notConfigured();
    }

    public function listPatientEncounters(string $providerPatientId, array $filters = []): array
    {
        return [];
    }

    // ─── Orders ───────────────────────────────────────────────────────────

    public function getOrder(string $orderId, array $include = []): array
    {
        throw $this->notConfigured();
    }

    public function listPatientOrders(string $providerPatientId, array $filters = []): array
    {
        return [];
    }

    public function previewCart(array $lines, ?string $couponCode = null, ?array $shippingAddress = null): array
    {
        throw $this->notConfigured();
    }

    public function validateCoupon(string $code, array $lines = []): array
    {
        throw $this->notConfigured();
    }

    // ─── Prescriptions ────────────────────────────────────────────────────

    public function listPatientPrescriptions(string $providerPatientId, array $filters = []): array
    {
        return [];
    }

    // ─── Subscriptions ────────────────────────────────────────────────────

    public function listPatientSubscriptions(string $providerPatientId): array
    {
        return [];
    }

    public function createSubscription(array $data): array
    {
        throw $this->notConfigured();
    }

    public function updateSubscription(string $subscriptionId, array $data): array
    {
        throw $this->notConfigured();
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        throw $this->notConfigured();
    }

    // ─── Lab orders ───────────────────────────────────────────────────────

    public function createLabOrder(array $data): array
    {
        throw $this->notConfigured();
    }

    public function listPatientLabOrders(string $providerPatientId): array
    {
        return [];
    }

    public function getLabCatalog(): array
    {
        return [];
    }

    // ─── Scheduling ───────────────────────────────────────────────────────

    public function getProviderAvailability(string $providerId, string $startDate, string $endDate): array
    {
        return [];
    }

    public function bookAppointment(array $data): array
    {
        throw $this->notConfigured();
    }

    public function listPatientAppointments(string $providerPatientId): array
    {
        return [];
    }

    // ─── Webhooks ─────────────────────────────────────────────────────────

    public function registerWebhook(string $eventType, string $endpointUrl): array
    {
        throw $this->notConfigured();
    }

    public function listWebhooks(): array
    {
        return [];
    }

    public function deleteWebhook(string $webhookId): void
    {
        throw $this->notConfigured();
    }

    // ─── Embed / patient portal ───────────────────────────────────────────

    public function buildEmbedPayload(Lead $lead): ?array
    {
        return null;
    }

    public function issuePatientToken(string $providerPatientId): ?string
    {
        return null;
    }

    // ─── Internals ────────────────────────────────────────────────────────

    protected function notConfigured(): \RuntimeException
    {
        return new \RuntimeException(
            'No telehealth provider configured. Enable a provider in Admin → Settings → Integrations.'
        );
    }
}
