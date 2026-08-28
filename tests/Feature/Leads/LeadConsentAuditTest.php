<?php

namespace Tests\Feature\Leads;

use App\Actions\Leads\RecordConsentAction;
use App\Models\Lead;
use App\Models\LeadConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LeadConsentAuditTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL_COPY = 'Email me my protocol plan and occasional offers.';

    // ─── Capture ─────────────────────────────────────────────────────

    public function test_the_sentence_the_visitor_saw_is_snapshotted_at_capture(): void
    {
        $this->postJson('/api/v1/leads', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'email_consent' => true,
            'consent_disclosures' => [
                'email' => ['text' => self::EMAIL_COPY, 'version' => '2026-08-28'],
            ],
        ])->assertStatus(201);

        // The point of the table: the operator can edit the quiz copy tomorrow
        // and this record still says what was actually agreed to.
        $this->assertDatabaseHas('lead_consents', [
            'channel' => 'email',
            'granted' => true,
            'consent_text' => self::EMAIL_COPY,
            'consent_version' => '2026-08-28',
        ]);
    }

    public function test_request_metadata_is_server_derived_not_taken_from_the_payload(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson('/api/v1/leads', [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
                'email_consent' => true,
                // A client claiming a different IP must not be believed.
                'ip_address' => '10.0.0.1',
            ], ['User-Agent' => 'PinnedAgent/1.0'])
            ->assertStatus(201);

        $consent = LeadConsent::query()->where('channel', 'email')->firstOrFail();

        $this->assertSame('203.0.113.9', $consent->ip_address);
        $this->assertSame('PinnedAgent/1.0', $consent->user_agent);
    }

    public function test_a_declined_channel_is_recorded_when_its_wording_was_shown(): void
    {
        $this->postJson('/api/v1/leads', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'email_consent' => true,
            'sms_consent' => false,
            'consent_disclosures' => [
                'email' => ['text' => self::EMAIL_COPY],
                'sms' => ['text' => 'Text me appointment reminders.'],
            ],
        ])->assertStatus(201);

        // "We showed them the SMS opt-in and they left it unticked" is evidence.
        // Without it, a later complaint is unanswerable.
        $this->assertDatabaseHas('lead_consents', ['channel' => 'sms', 'granted' => false]);
    }

    public function test_silence_about_a_channel_records_nothing(): void
    {
        $this->postJson('/api/v1/leads', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'email_consent' => true,
        ])->assertStatus(201);

        // Declined and never-asked are genuinely different, and this install
        // cannot tell them apart unless the client says which it showed. It
        // must not guess.
        $this->assertDatabaseMissing('lead_consents', ['channel' => 'sms']);
    }

    public function test_no_consent_and_no_disclosure_writes_no_audit_rows(): void
    {
        $this->postJson('/api/v1/leads', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ])->assertStatus(201);

        $this->assertSame(0, LeadConsent::query()->count());
    }

    // ─── Append-only ─────────────────────────────────────────────────

    public function test_a_consent_record_cannot_be_edited(): void
    {
        $consent = $this->recordConsent();

        $this->expectException(RuntimeException::class);

        $consent->update(['granted' => false]);
    }

    public function test_a_consent_record_cannot_be_deleted(): void
    {
        $consent = $this->recordConsent();

        $this->expectException(RuntimeException::class);

        $consent->delete();
    }

    public function test_a_withdrawal_is_a_new_row_and_leaves_the_grant_intact(): void
    {
        $lead = Lead::factory()->create(['email_consent' => true]);
        $action = app(RecordConsentAction::class);

        $action->execute($lead, 'email', true, text: self::EMAIL_COPY, source: 'quiz');
        $action->execute($lead, 'email', false, source: 'admin');

        $this->assertSame(2, LeadConsent::query()->where('channel', 'email')->count());
        $this->assertSame(1, LeadConsent::query()->where('granted', true)->count());
        $this->assertFalse((bool) $lead->fresh()->email_consent);
    }

    public function test_a_withdrawal_does_not_erase_when_consent_was_first_given(): void
    {
        $lead = Lead::factory()->create(['email_consent' => false, 'consent_given_at' => null]);
        $action = app(RecordConsentAction::class);

        $action->execute($lead, 'email', true, source: 'quiz');
        $grantedAt = $lead->fresh()->consent_given_at;
        $this->assertNotNull($grantedAt);

        $action->execute($lead, 'email', false, source: 'admin');

        // A withdrawal does not un-happen the original consent.
        $this->assertEquals($grantedAt, $lead->fresh()->consent_given_at);
    }

    public function test_an_operator_recorded_consent_is_distinguishable_from_a_visitor_one(): void
    {
        $lead = Lead::factory()->create();

        $consent = app(RecordConsentAction::class)
            ->execute($lead, 'email', true, source: 'admin', userId: null);

        $this->assertNull($consent->recorded_by_user_id);
        $this->assertSame('admin', $consent->source);
    }

    // ─── Summary stays in step with the audit ────────────────────────

    public function test_the_lead_boolean_mirrors_the_latest_audit_row(): void
    {
        $lead = Lead::factory()->create(['sms_consent' => false]);

        app(RecordConsentAction::class)->execute($lead, 'sms', true, source: 'quiz');

        $this->assertTrue((bool) $lead->fresh()->sms_consent);
    }

    private function recordConsent(): LeadConsent
    {
        return app(RecordConsentAction::class)->execute(
            Lead::factory()->create(),
            'email',
            true,
            text: self::EMAIL_COPY,
            source: 'quiz',
        );
    }
}
