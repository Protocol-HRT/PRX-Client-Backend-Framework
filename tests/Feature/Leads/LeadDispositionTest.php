<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadStatus;
use App\Events\Leads\LeadCreated;
use App\Events\Leads\LeadDispositionChanged;
use App\Models\Lead;
use App\Models\LeadDisposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class LeadDispositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The map is memoized per request; a test that edits dispositions and
        // then reads a label would otherwise see the previous test's rows.
        LeadDisposition::forgetMap();
    }

    // ─── Seeding and defaults ────────────────────────────────────────

    public function test_migration_seeds_the_four_statuses_the_code_writes(): void
    {
        foreach (LeadStatus::cases() as $case) {
            $this->assertDatabaseHas('lead_dispositions', [
                'slug' => $case->value,
                'is_system' => true,
            ]);
        }
    }

    public function test_new_lead_starts_on_the_configured_default_not_a_hardcoded_one(): void
    {
        LeadDisposition::query()->where('slug', LeadStatus::New_->value)->update(['is_default' => false]);
        LeadDisposition::create([
            'slug' => 'awaiting_review',
            'name' => 'Awaiting review',
            'is_default' => true,
        ]);

        $lead = Lead::factory()->create(['status' => null]);

        $this->assertSame('awaiting_review', $lead->fresh()->status);
    }

    public function test_only_one_disposition_can_be_default(): void
    {
        LeadDisposition::create(['slug' => 'a', 'name' => 'A', 'is_default' => true]);
        LeadDisposition::create(['slug' => 'b', 'name' => 'B', 'is_default' => true]);

        $this->assertSame(1, LeadDisposition::query()->where('is_default', true)->count());
        $this->assertSame('b', LeadDisposition::query()->where('is_default', true)->value('slug'));
    }

    public function test_default_slug_falls_back_when_no_row_is_marked_default(): void
    {
        LeadDisposition::query()->update(['is_default' => false]);

        $this->assertSame(LeadStatus::New_->value, LeadDisposition::defaultSlug());
    }

    // ─── The guards: a slug is a foreign key in all but name ─────────

    public function test_a_system_disposition_cannot_be_deleted(): void
    {
        $this->expectException(RuntimeException::class);

        LeadDisposition::query()->where('slug', LeadStatus::HandedOff->value)->first()->delete();
    }

    public function test_a_system_disposition_cannot_be_reslugged(): void
    {
        $this->expectException(RuntimeException::class);

        LeadDisposition::query()
            ->where('slug', LeadStatus::Completed->value)
            ->first()
            ->update(['slug' => 'finished']);
    }

    public function test_a_disposition_in_use_by_leads_cannot_be_deleted(): void
    {
        $disposition = LeadDisposition::create(['slug' => 'nurture', 'name' => 'Nurture']);
        Lead::factory()->create(['status' => 'nurture']);

        $this->expectException(RuntimeException::class);

        $disposition->delete();
    }

    public function test_a_disposition_in_use_by_leads_cannot_be_reslugged(): void
    {
        $disposition = LeadDisposition::create(['slug' => 'nurture', 'name' => 'Nurture']);
        Lead::factory()->create(['status' => 'nurture']);

        $this->expectException(RuntimeException::class);

        $disposition->update(['slug' => 'warming']);
    }

    public function test_an_unused_custom_disposition_can_be_renamed_and_deleted(): void
    {
        $disposition = LeadDisposition::create(['slug' => 'nurture', 'name' => 'Nurture']);

        // The LABEL is always free to change; only the slug is load-bearing.
        $disposition->update(['name' => 'Warming up', 'slug' => 'warming']);
        $this->assertSame('warming', $disposition->fresh()->slug);

        $disposition->delete();
        $this->assertDatabaseMissing('lead_dispositions', ['slug' => 'warming']);
    }

    public function test_deactivating_a_disposition_does_not_trap_the_leads_on_it(): void
    {
        LeadDisposition::create(['slug' => 'nurture', 'name' => 'Nurture', 'is_active' => false]);

        // Hidden from the pickers...
        $this->assertArrayNotHasKey('nurture', LeadDisposition::options());

        // ...but still offered to a lead that is actually sitting on it, or the
        // required Select renders empty and refuses to save.
        $this->assertArrayHasKey('nurture', LeadDisposition::optionsFor('nurture'));
        $this->assertSame('Nurture', LeadDisposition::optionsFor('nurture')['nurture']);
    }

    public function test_options_for_a_live_disposition_adds_nothing(): void
    {
        $this->assertSame(
            LeadDisposition::options(),
            LeadDisposition::optionsFor(LeadStatus::New_->value),
        );
    }

    // ─── Labels degrade visibly rather than silently ─────────────────

    public function test_an_orphaned_slug_renders_as_itself_rather_than_being_hidden(): void
    {
        $this->assertSame('gone_missing', LeadDisposition::labelFor('gone_missing'));
        $this->assertSame('gray', LeadDisposition::colorFor('gone_missing'));
    }

    // ─── Events ──────────────────────────────────────────────────────

    public function test_lead_created_fires_for_a_checkout_lead_with_no_quiz(): void
    {
        Event::fake([LeadCreated::class]);

        $this->postJson('/api/v1/leads', [
            'first_name' => 'Cart',
            'last_name' => 'Buyer',
            'email' => 'cart@example.com',
        ])->assertStatus(201);

        // The regression this exists to prevent: QuizCompleted is guarded by
        // `$quiz !== null`, so hanging welcome comms off it silently skipped
        // every checkout lead — the highest-intent leads the funnel produces.
        Event::assertDispatched(LeadCreated::class);
    }

    public function test_disposition_change_dispatches_an_event_carrying_both_ends(): void
    {
        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);

        Event::fake([LeadDispositionChanged::class]);

        $lead->update(['status' => LeadStatus::HandedOff->value]);

        Event::assertDispatched(LeadDispositionChanged::class, function (LeadDispositionChanged $e) use ($lead) {
            return $e->lead->is($lead)
                && $e->from === LeadStatus::New_->value
                && $e->to === LeadStatus::HandedOff->value;
        });
    }

    public function test_saving_without_changing_disposition_dispatches_nothing(): void
    {
        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);

        Event::fake([LeadDispositionChanged::class]);

        $lead->update(['first_name' => 'Renamed']);

        Event::assertNotDispatched(LeadDispositionChanged::class);
    }

    public function test_the_event_fires_however_the_status_moved(): void
    {
        // An observer rather than a dispatch per action, so a write path nobody
        // remembered still produces the event.
        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);

        Event::fake([LeadDispositionChanged::class]);

        Lead::query()->whereKey($lead->getKey())->first()->forceFill([
            'status' => 'nurture',
        ])->save();

        Event::assertDispatched(LeadDispositionChanged::class);
    }

    public function test_the_event_fires_for_a_write_inside_a_transaction(): void
    {
        // REGRESSION. The observer is $afterCommit, so its handler runs after
        // Eloquent has synced original away — `getOriginal('status')` then
        // returned the NEW value, `$from === $to`, and the guard swallowed the
        // event entirely. Every transactional status write dispatched nothing,
        // including SubmitPrescribeRxCheckoutAction's move to handed_off, while
        // direct writes worked fine and every test passed.
        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);

        Event::fake([LeadDispositionChanged::class]);

        DB::transaction(fn () => $lead->update(['status' => LeadStatus::HandedOff->value]));

        Event::assertDispatched(LeadDispositionChanged::class, fn (LeadDispositionChanged $e): bool => $e->from === LeadStatus::New_->value && $e->to === LeadStatus::HandedOff->value);
    }

    // ─── The wire format did not move ────────────────────────────────

    public function test_the_api_still_emits_the_status_slug_as_a_plain_string(): void
    {
        $this->postJson('/api/v1/leads', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', LeadStatus::New_->value);
    }
}
