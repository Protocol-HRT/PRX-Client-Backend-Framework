<?php

namespace Tests\Feature;

use App\Actions\Referral\AttributeLeadAction;
use App\Actions\Referral\RecordReferralClickAction;
use App\Events\Leads\LeadCreated;
use App\Models\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Attribution is money, so these assert DURABILITY properties, not just wiring:
 * that a click survives its source being deleted, that a credit is never
 * reassigned, and that a code nobody recognises is still recorded.
 */
class ReferralAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function click(): RecordReferralClickAction
    {
        return app(RecordReferralClickAction::class);
    }

    private function attribute(): AttributeLeadAction
    {
        return app(AttributeLeadAction::class);
    }

    public function test_a_click_resolves_to_its_source(): void
    {
        $source = ReferralSource::factory()->create();
        $link = ReferralLink::factory()->for($source, 'source')->create(['code' => 'lt-4f2a']);

        $click = $this->click()->execute('lt-4f2a', (string) Str::uuid(), [
            'landing_url' => 'https://atlas.test/?ref=LT-4F2A',
        ]);

        $this->assertSame($link->id, $click->referral_link_id);
        $this->assertSame($source->id, $click->referral_source_id);
        $this->assertSame($source->slug, $click->source_slug);
    }

    public function test_codes_are_matched_case_insensitively(): void
    {
        $link = ReferralLink::factory()->create(['code' => 'lt-4f2a']);

        $click = $this->click()->execute('LT-4F2A', (string) Str::uuid());

        $this->assertSame($link->id, $click->referral_link_id);
        $this->assertSame('lt-4f2a', $click->code);
    }

    public function test_an_unknown_code_is_still_recorded_as_evidence(): void
    {
        $click = $this->click()->execute('never-issued', (string) Str::uuid());

        $this->assertNotNull($click);
        $this->assertSame('never-issued', $click->code);
        $this->assertNull($click->referral_source_id);
    }

    public function test_a_visitor_refreshing_the_landing_page_mints_one_click(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);
        $visitor = (string) Str::uuid();

        $first = $this->click()->execute('lt-4f2a', $visitor);
        $second = $this->click()->execute('lt-4f2a', $visitor);
        $third = $this->click()->execute('LT-4F2A', $visitor);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, ReferralClick::where('code', 'lt-4f2a')->count());
    }

    public function test_two_visitors_mint_two_clicks(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);

        $this->click()->execute('lt-4f2a', (string) Str::uuid());
        $this->click()->execute('lt-4f2a', (string) Str::uuid());

        $this->assertSame(2, ReferralClick::where('code', 'lt-4f2a')->count());
    }

    public function test_a_forged_visitor_id_is_refused(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);

        $this->assertNull($this->click()->execute('lt-4f2a', 'not-a-uuid'));
        $this->assertSame(0, ReferralClick::count());
    }

    public function test_an_expired_or_inactive_link_records_an_unresolved_click(): void
    {
        ReferralLink::factory()->expired()->create(['code' => 'gone']);
        ReferralLink::factory()->inactive()->create(['code' => 'off']);

        $expired = $this->click()->execute('gone', (string) Str::uuid());
        $inactive = $this->click()->execute('off', (string) Str::uuid());

        $this->assertNull($expired->referral_source_id);
        $this->assertNull($inactive->referral_source_id);
        // ...but both are on the record.
        $this->assertSame(2, ReferralClick::count());
    }

    public function test_deactivating_a_source_stops_its_links_earning_immediately(): void
    {
        $source = ReferralSource::factory()->inactive()->create();
        ReferralLink::factory()->for($source, 'source')->create(['code' => 'lt-4f2a']);

        $click = $this->click()->execute('lt-4f2a', (string) Str::uuid());

        $this->assertNull($click->referral_source_id);
    }

    /** THE DURABILITY PROPERTY: the ledger outlives the records it points at. */
    public function test_a_click_still_names_its_source_after_the_source_is_deleted(): void
    {
        $source = ReferralSource::factory()->create(['slug' => 'acme-partners']);
        $link = ReferralLink::factory()->for($source, 'source')->create(['code' => 'lt-4f2a']);

        $click = $this->click()->execute('lt-4f2a', (string) Str::uuid());

        $link->forceDelete();
        $source->forceDelete();

        $click->refresh();

        $this->assertNull($click->referral_source_id);
        $this->assertNull($click->referral_link_id);
        $this->assertSame('lt-4f2a', $click->code);
        $this->assertSame('acme-partners', $click->source_slug);
    }

    /**
     * A source holding links cannot be hard-deleted at all. prescribe-rx's own
     * schema cascades here, which silently nulls attribution on every lead the
     * source produced; we would rather the delete fail loudly.
     */
    public function test_a_source_with_links_cannot_be_force_deleted(): void
    {
        $source = ReferralSource::factory()->create();
        ReferralLink::factory()->for($source, 'source')->create();

        $this->expectException(QueryException::class);

        $source->forceDelete();
    }

    public function test_a_lead_is_attributed_to_the_click_that_produced_it(): void
    {
        $source = ReferralSource::factory()->create();
        $link = ReferralLink::factory()->for($source, 'source')->create(['code' => 'lt-4f2a']);
        $visitor = (string) Str::uuid();

        $click = $this->click()->execute('lt-4f2a', $visitor, [
            'landing_url' => 'https://atlas.test/stacks?ref=LT-4F2A',
            'utm_source' => 'newsletter',
        ]);

        $lead = Lead::factory()->create(['referral_code' => null, 'utm_source' => null, 'landing_url' => null]);
        $this->attribute()->execute($lead, 'LT-4F2A', $visitor);

        $lead->refresh();
        $this->assertSame($source->id, $lead->referral_source_id);
        $this->assertSame($link->id, $lead->referral_link_id);
        $this->assertSame($click->id, $lead->referral_click_id);
        $this->assertSame('lt-4f2a', $lead->referral_code);
        $this->assertNotNull($lead->attributed_at);

        // The landing's own attribution is backfilled — this is the defect being
        // fixed, where the storefront read utm at submit time or not at all.
        $this->assertSame('newsletter', $lead->utm_source);
        $this->assertSame('https://atlas.test/stacks?ref=LT-4F2A', $lead->landing_url);
    }

    public function test_attribution_is_write_once_and_never_reassigned(): void
    {
        $first = ReferralLink::factory()->create(['code' => 'first']);
        ReferralLink::factory()->create(['code' => 'second']);

        $lead = Lead::factory()->create(['referral_code' => null]);
        $this->attribute()->execute($lead, 'first');
        $this->attribute()->execute($lead->refresh(), 'second');

        $lead->refresh();
        $this->assertSame('first', $lead->referral_code);
        $this->assertSame($first->referral_source_id, $lead->referral_source_id);
    }

    public function test_a_referral_survives_a_lost_cookie_by_falling_back_to_the_code(): void
    {
        $source = ReferralSource::factory()->create();
        ReferralLink::factory()->for($source, 'source')->create(['code' => 'lt-4f2a']);

        $lead = Lead::factory()->create(['referral_code' => null]);
        // No visitor id at all — a different device, or cleared storage.
        $this->attribute()->execute($lead, 'lt-4f2a', null);

        $lead->refresh();
        $this->assertSame($source->id, $lead->referral_source_id);
        $this->assertNull($lead->referral_click_id);
    }

    public function test_the_endpoint_records_a_click(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);

        $this->postJson('/api/v1/referrals/clicks', [
            'code' => 'LT-4F2A',
            'visitor_id' => (string) Str::uuid(),
            'landing_url' => 'https://atlas.test/?ref=LT-4F2A',
        ])->assertOk()->assertJson(['data' => ['recorded' => true]]);

        $this->assertSame(1, ReferralClick::count());
    }

    public function test_the_endpoint_accepts_an_unknown_code_without_failing(): void
    {
        $this->postJson('/api/v1/referrals/clicks', [
            'code' => 'never-issued',
            'visitor_id' => (string) Str::uuid(),
        ])->assertOk()->assertJson(['data' => ['recorded' => true]]);
    }

    public function test_the_endpoint_rejects_a_missing_visitor_id(): void
    {
        $this->postJson('/api/v1/referrals/clicks', ['code' => 'lt-4f2a'])
            ->assertStatus(422)->assertJsonValidationErrors('visitor_id');
    }

    /** The endpoint must never become an oracle for enumerating live codes. */
    public function test_the_endpoint_leaks_no_source_detail(): void
    {
        $source = ReferralSource::factory()->create(['name' => 'Acme Partners']);
        ReferralLink::factory()->for($source, 'source')->create(['code' => 'lt-4f2a']);

        $response = $this->postJson('/api/v1/referrals/clicks', [
            'code' => 'lt-4f2a',
            'visitor_id' => (string) Str::uuid(),
        ])->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsString('Acme Partners', $body);
        $this->assertStringNotContainsString($source->slug, $body);
        // The response says nothing about the code at all — not even whether it
        // is live, which would be one bit per probe toward enumerating the
        // affiliate roster.
        $this->assertSame(['recorded'], array_keys($response->json('data')));
    }

    public function test_lead_capture_binds_a_referral_end_to_end(): void
    {
        $source = ReferralSource::factory()->create();
        ReferralLink::factory()->for($source, 'source')->create(['code' => 'lt-4f2a']);
        $visitor = (string) Str::uuid();

        $this->postJson('/api/v1/referrals/clicks', [
            'code' => 'lt-4f2a',
            'visitor_id' => $visitor,
            'landing_url' => 'https://atlas.test/?ref=lt-4f2a',
        ])->assertOk();

        $this->postJson('/api/v1/leads', [
            'first_name' => 'Dana',
            'last_name' => 'Reyes',
            'email' => 'dana@example.test',
            'referral_code' => 'lt-4f2a',
            'referral_visitor_id' => $visitor,
        ])->assertCreated();

        $lead = Lead::where('email', 'dana@example.test')->firstOrFail();
        $this->assertSame($source->id, $lead->referral_source_id);
        $this->assertSame('https://atlas.test/?ref=lt-4f2a', $lead->landing_url);
    }

    /**
     * Unique clicks are DERIVED, never stored. If a counter column ever appears
     * on referral_links, this is the test that should be read before adding it.
     */
    public function test_click_counts_are_derivable_from_the_ledger_alone(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);
        $repeat = (string) Str::uuid();

        $this->click()->execute('lt-4f2a', $repeat);
        $this->click()->execute('lt-4f2a', $repeat);
        $this->click()->execute('lt-4f2a', (string) Str::uuid());

        $total = ReferralClick::where('code', 'lt-4f2a')->count();
        $unique = ReferralClick::where('code', 'lt-4f2a')->distinct('visitor_id')->count('visitor_id');

        $this->assertSame(2, $total);
        $this->assertSame(2, $unique);
    }

    /**
     * THE BLOCKING DEFECT THE REVIEW GATE CAUGHT. Unicode lowercasing can lengthen
     * a string, so a 64-character code of `İ` becomes 128 once lowered. Bounding
     * before lowering let that into a 30-day cookie, after which every lead submit
     * carried an over-long code — 422 on the quiz, and a column overflow 500 on
     * the direct path AFTER the lead had been created.
     */
    public function test_a_code_that_lengthens_when_lowercased_cannot_break_lead_capture(): void
    {
        $hostile = str_repeat('İ', 64);

        $this->assertSame(128, mb_strlen(Str::lower($hostile)));

        // Refused as a code...
        $this->assertNull(ReferralLink::normalizeCode($hostile));
        $this->assertNull($this->click()->execute($hostile, (string) Str::uuid()));

        // ...and the LEAD is still captured, which is the property that matters.
        $this->postJson('/api/v1/leads', [
            'first_name' => 'Sam',
            'last_name' => 'Okafor',
            'email' => 'sam@example.test',
            'referral_code' => $hostile,
        ])->assertCreated();

        $lead = Lead::where('email', 'sam@example.test')->firstOrFail();
        $this->assertNull($lead->referral_code);
    }

    public function test_a_hostile_code_does_not_break_the_click_endpoint(): void
    {
        $this->postJson('/api/v1/referrals/clicks', [
            'code' => str_repeat('İ', 64),
            'visitor_id' => (string) Str::uuid(),
        ])->assertOk()->assertJson(['data' => ['recorded' => false]]);

        $this->assertSame(0, ReferralClick::count());
    }

    /**
     * Write-once must survive a careless writer, not only the action's own guard.
     * The referral columns are deliberately absent from Lead::$fillable so that
     * forceFill in AttributeLeadAction is the only door.
     */
    public function test_mass_assignment_cannot_reassign_a_credit(): void
    {
        $first = ReferralLink::factory()->create(['code' => 'first']);
        // A REAL second link, so a mutation that re-adds these to $fillable fails
        // on the assertion below rather than erroring on a foreign key — an
        // exception would mask the property this test exists to pin.
        $second = ReferralLink::factory()->create(['code' => 'second']);

        $lead = Lead::factory()->create();
        $this->attribute()->execute($lead, 'first');

        $lead->refresh()->update([
            'referral_code' => 'second',
            'referral_source_id' => $second->referral_source_id,
            'referral_link_id' => $second->id,
        ]);

        $lead->refresh();
        $this->assertSame('first', $lead->referral_code);
        $this->assertSame($first->referral_source_id, $lead->referral_source_id);
    }

    /**
     * The click holds the REAL landing; the form holds whatever the URL said at
     * submit time. Where they disagree the click wins — that is the defect this
     * whole feature exists to fix, so a tie-break in the other direction would
     * quietly reinstate it.
     */
    public function test_the_click_landing_beats_what_the_form_submitted(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);
        $visitor = (string) Str::uuid();

        $this->click()->execute('lt-4f2a', $visitor, [
            'landing_url' => 'https://atlas.test/?ref=lt-4f2a',
            'utm_source' => 'flyer',
        ]);

        // What quizClient's attribution() would send: the QUIZ page, not the landing.
        $lead = Lead::factory()->create([
            'landing_url' => 'https://atlas.test/quiz',
            'utm_source' => null,
        ]);

        $this->attribute()->execute($lead, 'lt-4f2a', $visitor);

        $lead->refresh();
        $this->assertSame('https://atlas.test/?ref=lt-4f2a', $lead->landing_url);
        $this->assertSame('flyer', $lead->utm_source);
    }

    /** A concurrent double landing may not inflate a raw click count. */
    public function test_the_unique_index_makes_one_click_per_visitor_structural(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);
        $visitor = (string) Str::uuid();

        $this->click()->execute('lt-4f2a', $visitor);

        $this->expectException(QueryException::class);

        // Bypasses the action entirely, the way a race would.
        ReferralClick::factory()->create(['code' => 'lt-4f2a', 'visitor_id' => $visitor]);
    }

    /**
     * Workflow conditions can read `utm_*` (WorkflowServiceProvider's allow-list),
     * and those are backfilled from the click. So attribution must complete BEFORE
     * `lead.created` fires, or a queued chain routes a referred visitor on the
     * pre-backfill values.
     */
    public function test_attribution_is_complete_before_the_lead_created_event_fires(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);
        $visitor = (string) Str::uuid();

        $this->click()->execute('lt-4f2a', $visitor, ['utm_source' => 'flyer']);

        $seen = null;
        Event::listen(LeadCreated::class, function (LeadCreated $event) use (&$seen): void {
            $seen = [
                'code' => $event->lead->referral_code,
                'utm_source' => $event->lead->utm_source,
            ];
        });

        $this->postJson('/api/v1/leads', [
            'first_name' => 'Ada',
            'last_name' => 'Vance',
            'email' => 'ada@example.test',
            'referral_code' => 'lt-4f2a',
            'referral_visitor_id' => $visitor,
        ])->assertCreated();

        $this->assertSame('lt-4f2a', $seen['code']);
        $this->assertSame('flyer', $seen['utm_source']);
    }

    /**
     * The utm tuple is replaced as a SET. Merging field by field invents a
     * source/medium pairing that never occurred, which is worse than either
     * alone because it looks like real campaign data.
     */
    public function test_the_click_replaces_the_whole_utm_tuple_not_field_by_field(): void
    {
        ReferralLink::factory()->create(['code' => 'lt-4f2a']);
        $visitor = (string) Str::uuid();

        // The landing carried only a source.
        $this->click()->execute('lt-4f2a', $visitor, ['utm_source' => 'flyer']);

        // The submit-time URL carried a full, unrelated paid-search tuple.
        $lead = Lead::factory()->create([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'brand',
        ]);

        $this->attribute()->execute($lead, 'lt-4f2a', $visitor);

        $lead->refresh();
        $this->assertSame('flyer', $lead->utm_source);
        $this->assertNull($lead->utm_medium);
        $this->assertNull($lead->utm_campaign);
    }
}
