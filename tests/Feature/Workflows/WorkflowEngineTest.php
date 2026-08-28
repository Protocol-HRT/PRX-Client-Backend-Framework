<?php

namespace Tests\Feature\Workflows;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadDisposition;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowAction;
use App\Models\Workflow\WorkflowActionRun;
use App\Models\Workflow\WorkflowRun;
use App\Workflows\WorkflowRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LeadDisposition::forgetMap();
        LeadDisposition::firstOrCreate(['slug' => 'quiz_complete'], ['name' => 'Quiz complete']);
        LeadDisposition::firstOrCreate(['slug' => 'nurture'], ['name' => 'Nurture']);
    }

    // ─── Matching ────────────────────────────────────────────────────

    public function test_a_workflow_runs_when_its_conditions_match(): void
    {
        $this->workflow(conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'quiz_complete'],
        ]);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'quiz_complete']);

        $run = WorkflowRun::query()->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame(WorkflowRun::STATUS_COMPLETED, $run->status);
    }

    public function test_a_skipped_run_is_recorded_and_names_the_condition_that_rejected_it(): void
    {
        $this->workflow(conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'quiz_complete'],
        ]);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'nurture']);

        $run = WorkflowRun::query()->latest('id')->first();

        // "Why didn't my workflow fire?" has to be answerable from the log.
        $this->assertSame(WorkflowRun::STATUS_SKIPPED, $run->status);
        $this->assertStringContainsString('status', $run->skip_reason);
        $this->assertStringContainsString('nurture', $run->skip_reason);
    }

    public function test_a_transition_can_be_expressed_without_forking_the_condition_evaluator(): void
    {
        // "became quiz_complete FROM new" — two ordinary VisibleWhen conditions
        // over the reserved `_original.` namespace.
        $this->workflow(conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'quiz_complete'],
            ['field' => '_original.status', 'operator' => 'equals', 'value' => LeadStatus::New_->value],
        ]);

        $fromNew = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $fromNew->update(['status' => 'quiz_complete']);
        $this->assertSame(WorkflowRun::STATUS_COMPLETED, WorkflowRun::query()->latest('id')->first()->status);

        $fromNurture = Lead::factory()->create(['status' => 'nurture']);
        $fromNurture->update(['status' => 'quiz_complete']);
        $this->assertSame(WorkflowRun::STATUS_SKIPPED, WorkflowRun::query()->latest('id')->first()->status);
    }

    public function test_a_write_that_changed_nothing_is_not_a_trigger(): void
    {
        $this->workflow();

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => LeadStatus::New_->value]);

        $this->assertSame(0, WorkflowRun::query()->count());
    }

    public function test_an_inactive_workflow_never_runs(): void
    {
        $this->workflow(active: false);

        Lead::factory()->create()->update(['status' => 'nurture']);

        $this->assertSame(0, WorkflowRun::query()->count());
    }

    public function test_original_survives_a_write_inside_a_transaction(): void
    {
        // REGRESSION, same root cause as the LeadObserver one: the observer is
        // $afterCommit, so a snapshot taken in the handler reads post-sync values
        // and the documented "moved to X from Y" pattern could NEVER match inside
        // a transaction — which is where the checkout actions move status.
        $this->workflow(conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'quiz_complete'],
            ['field' => '_original.status', 'operator' => 'equals', 'value' => LeadStatus::New_->value],
        ]);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);

        DB::transaction(fn () => $lead->update(['status' => 'quiz_complete']));

        $this->assertSame(WorkflowRun::STATUS_COMPLETED, WorkflowRun::query()->latest('id')->first()->status);
    }

    public function test_original_is_per_write_and_not_inherited_by_a_chained_one(): void
    {
        // A nested dispatch fired from inside an outer save must see ITS OWN
        // previous value, not the outer write's.
        $a = $this->workflow(slug: 'a', priority: 1, conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'nurture'],
        ]);
        $this->action($a, 'update_field', ['field' => 'status', 'value' => 'quiz_complete']);

        $b = $this->workflow(slug: 'b', priority: 2, conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'quiz_complete'],
            ['field' => '_original.status', 'operator' => 'equals', 'value' => 'nurture'],
        ]);
        $this->action($b, 'update_field', ['field' => 'first_name', 'value' => 'B saw the right original']);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'nurture']);

        $this->assertSame('B saw the right original', $lead->fresh()->first_name);
    }

    // ─── Actions ─────────────────────────────────────────────────────

    public function test_update_field_moves_the_subject(): void
    {
        // Fires only when `email` was the thing that changed, which also pins
        // that `_changed.*` is per-attribute rather than "anything moved".
        $workflow = $this->workflow(conditions: [
            ['field' => '_changed.email', 'operator' => 'equals', 'value' => '1'],
        ]);
        $this->action($workflow, 'update_field', ['field' => 'status', 'value' => 'quiz_complete']);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);

        $lead->update(['first_name' => 'NotTheTrigger']);
        $this->assertSame(LeadStatus::New_->value, $lead->fresh()->status);

        $lead->update(['email' => 'moved@example.com']);
        $this->assertSame('quiz_complete', $lead->fresh()->status);
    }

    public function test_writing_an_unregistered_field_fails_loudly_rather_than_silently(): void
    {
        $workflow = $this->workflow();
        // `notes` is a real column but is NOT on the subject's allow-list.
        $this->action($workflow, 'update_field', ['field' => 'notes', 'value' => 'nope']);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'nurture']);

        $actionRun = WorkflowActionRun::query()->latest('id')->first();

        $this->assertSame(WorkflowActionRun::STATUS_FAILED, $actionRun->status);
        $this->assertStringContainsString('not writable', $actionRun->error);
        $this->assertNull($lead->fresh()->notes);
    }

    public function test_an_unregistered_job_is_refused(): void
    {
        $workflow = $this->workflow();
        $this->action($workflow, 'dispatch_job', ['job' => 'definitely_not_registered']);

        Lead::factory()->create(['status' => LeadStatus::New_->value])->update(['status' => 'nurture']);

        $actionRun = WorkflowActionRun::query()->latest('id')->first();

        $this->assertSame(WorkflowActionRun::STATUS_FAILED, $actionRun->status);
        $this->assertStringContainsString('not registered', $actionRun->error);
    }

    public function test_a_webhook_returning_an_error_status_is_recorded_as_a_failure(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $workflow = $this->workflow();
        $this->action($workflow, 'webhook', ['url' => 'https://example.test/hook']);

        Lead::factory()->create(['status' => LeadStatus::New_->value])->update(['status' => 'nurture']);

        $actionRun = WorkflowActionRun::query()->latest('id')->first();

        // Recording "we called it" while the far end 500s is how a broken
        // integration looks healthy for a month.
        $this->assertSame(WorkflowActionRun::STATUS_FAILED, $actionRun->status);
        $this->assertStringContainsString('500', $actionRun->error);
    }

    public function test_a_failing_action_does_not_stop_the_next_one_unless_told_to(): void
    {
        $workflow = $this->workflow();
        $this->action($workflow, 'dispatch_job', ['job' => 'missing'], sort: 1);
        $this->action($workflow, 'update_field', ['field' => 'status', 'value' => 'nurture'], sort: 2);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['first_name' => 'Go']);

        // A marketing push that fails must not prevent the status update behind it.
        $this->assertSame('nurture', $lead->fresh()->status);

        // The run that ACTED is the one that reports failure. (A later suppressed
        // row exists too, because the status write re-entered the trigger.)
        $this->assertSame(WorkflowRun::STATUS_FAILED, WorkflowRun::query()->oldest('id')->first()->status);
    }

    public function test_halt_on_failure_stops_the_remaining_actions(): void
    {
        $workflow = $this->workflow();
        $this->action($workflow, 'dispatch_job', ['job' => 'missing'], sort: 1, halt: true);
        $this->action($workflow, 'update_field', ['field' => 'status', 'value' => 'nurture'], sort: 2);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['first_name' => 'Go']);

        $this->assertSame(LeadStatus::New_->value, $lead->fresh()->status);
        $this->assertSame(1, WorkflowActionRun::query()->count());
    }

    // ─── Loop protection: the thing that would take a database down ──

    public function test_a_workflow_that_updates_the_field_it_watches_runs_once_not_forever(): void
    {
        // The FIRST thing an operator will build, and an infinite cycle without
        // the re-entry guard: moving the disposition re-fires model_updated,
        // which matches this same workflow again.
        $workflow = $this->workflow(conditions: [
            ['field' => 'status', 'operator' => 'not_equals', 'value' => 'quiz_complete'],
        ]);
        $this->action($workflow, 'update_field', ['field' => 'status', 'value' => 'quiz_complete']);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['first_name' => 'Kickoff']);

        $this->assertSame('quiz_complete', $lead->fresh()->status);

        // Acted once. Here the workflow's OWN condition stops the second pass —
        // the guard is not even reached, which is the healthy case.
        $this->assertSame(1, WorkflowRun::query()
            ->where('workflow_id', $workflow->id)
            ->where('status', '!=', WorkflowRun::STATUS_SKIPPED)
            ->count());
    }

    public function test_a_workflow_whose_condition_stays_true_is_stopped_by_the_re_entry_guard(): void
    {
        // The dangerous shape: the action does NOT falsify the condition, so
        // nothing but the guard stands between this and an infinite chain.
        $workflow = $this->workflow(conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'nurture'],
        ]);
        $this->action($workflow, 'update_field', ['field' => 'first_name', 'value' => 'Touched']);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'nurture']);

        $this->assertSame('Touched', $lead->fresh()->first_name);

        $this->assertSame(1, WorkflowRun::query()
            ->where('workflow_id', $workflow->id)
            ->where('status', '!=', WorkflowRun::STATUS_SKIPPED)
            ->count());

        // The withheld pass is RECORDED rather than silent, so the run log does
        // not go quiet at the exact moment it is most needed.
        $suppressed = WorkflowRun::query()
            ->where('workflow_id', $workflow->id)
            ->where('status', WorkflowRun::STATUS_SKIPPED)
            ->first();

        $this->assertNotNull($suppressed);
        $this->assertStringContainsString('prevent a loop', $suppressed->skip_reason);
    }

    public function test_two_workflows_updating_each_others_fields_terminate(): void
    {
        // Re-entry alone does not catch this: neither workflow repeats for the
        // same subject until the second lap. The depth cap does.
        $a = $this->workflow(slug: 'a', conditions: [['field' => 'status', 'operator' => 'equals', 'value' => 'nurture']]);
        $this->action($a, 'update_field', ['field' => 'status', 'value' => 'quiz_complete']);

        $b = $this->workflow(slug: 'b', conditions: [['field' => 'status', 'operator' => 'equals', 'value' => 'quiz_complete']]);
        $this->action($b, 'update_field', ['field' => 'status', 'value' => 'nurture']);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'nurture']);

        // Terminates, and the bound is small enough that a runaway is caught in
        // milliseconds rather than after thousands of rows.
        $this->assertLessThan(20, WorkflowRun::query()->count());
    }

    public function test_a_workflow_that_skipped_can_still_fire_later_in_the_same_chain(): void
    {
        // B is evaluated FIRST and skips, because status is still 'nurture'.
        $b = $this->workflow(slug: 'b', priority: 1, conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'quiz_complete'],
        ]);
        $this->action($b, 'update_field', ['field' => 'first_name', 'value' => 'B ran']);

        // A then makes B's condition true.
        $a = $this->workflow(slug: 'a', priority: 2, conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'nurture'],
        ]);
        $this->action($a, 'update_field', ['field' => 'status', 'value' => 'quiz_complete']);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'nurture']);

        // A skipped run performs no actions and so creates no new triggers — it
        // cannot be part of a loop, and must not be suppressed for the rest of
        // the chain.
        $this->assertSame('B ran', $lead->fresh()->first_name);
    }

    // ─── Ordering ────────────────────────────────────────────────────

    public function test_stop_on_first_match_stops_on_a_match_and_not_on_a_skip(): void
    {
        // Priority 1 SKIPS (its condition fails) and must not claim the trigger.
        $skipper = $this->workflow(slug: 'skipper', priority: 1, stopOnMatch: true, conditions: [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'never_matches'],
        ]);

        $matcher = $this->workflow(slug: 'matcher', priority: 2);
        $this->action($matcher, 'update_field', ['field' => 'first_name', 'value' => 'Reached']);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'nurture']);

        $this->assertSame('Reached', $lead->fresh()->first_name);
        $this->assertSame(WorkflowRun::STATUS_SKIPPED,
            WorkflowRun::query()->where('workflow_id', $skipper->id)->first()->status);
    }

    // ─── Event triggers ──────────────────────────────────────────────

    public function test_an_event_trigger_fires_for_every_lead_including_checkout_leads(): void
    {
        Workflow::create([
            'name' => 'On capture',
            'slug' => 'on-capture',
            'trigger_type' => 'event_fired',
            'trigger_target' => 'lead.created',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/leads', [
            'first_name' => 'Cart',
            'last_name' => 'Buyer',
            'email' => 'cart@example.com',
        ])->assertStatus(201);

        $this->assertSame(1, WorkflowRun::query()->where('trigger_type', 'event_fired')->count());
    }

    public function test_an_events_changed_field_is_declared_not_guessed(): void
    {
        // `lead.created` declares NO changed field, so `_changed.status` must be
        // blank on it. Guessing 'status' from an event's from/to would make this
        // condition fire on an event about something else entirely — the failure
        // the first adopter with a PriceChanged event would hit.
        $this->workflow(trigger: 'event_fired', target: 'lead.created', conditions: [
            ['field' => '_changed.status', 'operator' => 'equals', 'value' => '1'],
        ]);

        $this->postJson('/api/v1/leads', [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'decl@example.com',
        ])->assertStatus(201);

        $this->assertSame(WorkflowRun::STATUS_SKIPPED, WorkflowRun::query()->latest('id')->first()->status);
    }

    public function test_a_disposition_change_event_reports_the_field_it_declared(): void
    {
        $this->workflow(trigger: 'event_fired', target: 'lead.disposition_changed', conditions: [
            ['field' => '_original.status', 'operator' => 'equals', 'value' => LeadStatus::New_->value],
        ]);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value]);
        $lead->update(['status' => 'nurture']);

        $run = WorkflowRun::query()->where('trigger_type', 'event_fired')->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame(WorkflowRun::STATUS_COMPLETED, $run->status);
    }

    public function test_a_webhook_cannot_be_pointed_at_a_non_http_scheme(): void
    {
        $workflow = $this->workflow();
        $this->action($workflow, 'webhook', ['url' => 'file:///etc/passwd']);

        Lead::factory()->create(['status' => LeadStatus::New_->value])->update(['status' => 'nurture']);

        $actionRun = WorkflowActionRun::query()->latest('id')->first();

        $this->assertSame(WorkflowActionRun::STATUS_FAILED, $actionRun->status);
        $this->assertStringContainsString('http or https', $actionRun->error);
    }

    // ─── The registry is the security boundary ───────────────────────

    public function test_conditions_cannot_read_a_field_the_install_did_not_register(): void
    {
        $this->workflow(conditions: [
            // A real column, deliberately absent from the subject's field list.
            ['field' => 'ip_address', 'operator' => 'not_equals', 'value' => ''],
        ]);

        $lead = Lead::factory()->create(['status' => LeadStatus::New_->value, 'ip_address' => '203.0.113.9']);
        $lead->update(['status' => 'nurture']);

        // Reads fail closed, so the condition sees null and does not match.
        $this->assertSame(WorkflowRun::STATUS_SKIPPED, WorkflowRun::query()->latest('id')->first()->status);
    }

    public function test_an_unregistered_subject_key_matches_nothing(): void
    {
        $this->assertNull(app(WorkflowRegistry::class)->subject('not_a_thing'));
        $this->assertFalse(app(WorkflowRegistry::class)->allowsField('not_a_thing', 'status'));
    }

    // ─── helpers ─────────────────────────────────────────────────────

    private function workflow(
        string $slug = 'wf',
        array $conditions = [],
        bool $active = true,
        int $priority = 0,
        bool $stopOnMatch = false,
        string $trigger = 'model_updated',
        string $target = 'lead',
    ): Workflow {
        return Workflow::create([
            'name' => $slug,
            'slug' => $slug,
            'trigger_type' => $trigger,
            'trigger_target' => $target,
            'conditions' => $conditions,
            'is_active' => $active,
            'priority' => $priority,
            'stop_on_first_match' => $stopOnMatch,
        ]);
    }

    private function action(Workflow $workflow, string $type, array $config, int $sort = 0, bool $halt = false): WorkflowAction
    {
        return WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'action_type' => $type,
            'config' => $config,
            'is_active' => true,
            'sort_order' => $sort,
            'halt_on_failure' => $halt,
        ]);
    }
}
