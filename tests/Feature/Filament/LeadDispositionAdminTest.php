<?php

namespace Tests\Feature\Filament;

use App\Actions\Leads\RecordConsentAction;
use App\Enums\LeadStatus;
use App\Filament\Resources\LeadDispositions\Pages\CreateLeadDisposition;
use App\Filament\Resources\LeadDispositions\Pages\EditLeadDisposition;
use App\Filament\Resources\LeadDispositions\Pages\ListLeadDispositions;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\RelationManagers\ConsentsRelationManager;
use App\Integrations\ConsentResolver;
use App\Models\Lead;
use App\Models\LeadConsent;
use App\Models\LeadDisposition;
use App\Models\Quiz\Quiz;
use App\Models\Quiz\QuizQuestion;
use App\Models\Quiz\QuizStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The admin screens for dispositions, the consent audit and the quiz answers
 * actually render.
 *
 * Static review cannot catch a mistyped icon constant, a Blade view that does
 * not exist, or a column bound to a relationship that is not there — those all
 * fail only when Livewire mounts the page. Every one of those was a real risk
 * in this change: it introduced a new resource, a new relation manager and a
 * new Blade view at once.
 */
class LeadDispositionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LeadDisposition::forgetMap();

        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_the_disposition_list_renders_with_the_seeded_rows(): void
    {
        Livewire::test(ListLeadDispositions::class)
            ->assertOk()
            ->assertCanSeeTableRecords(LeadDisposition::all());
    }

    public function test_a_disposition_can_be_created_from_the_admin(): void
    {
        Livewire::test(CreateLeadDisposition::class)
            ->fillForm([
                'name' => 'Quiz complete',
                'slug' => 'quiz_complete',
                'color' => 'success',
                'sort_order' => 15,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('lead_dispositions', [
            'slug' => 'quiz_complete',
            'is_system' => false,
        ]);
    }

    public function test_the_edit_form_renders_for_a_system_disposition(): void
    {
        $record = LeadDisposition::query()->where('slug', LeadStatus::New_->value)->firstOrFail();

        // The slug field is disabled here rather than absent, so the page must
        // still mount cleanly.
        Livewire::test(EditLeadDisposition::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertFormSet(['slug' => LeadStatus::New_->value]);
    }

    public function test_the_lead_edit_page_renders_including_the_quiz_answers_tab(): void
    {
        $lead = $this->leadWithQuizAnswers();

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->assertOk()
            // The label comes from the question; the raw slug is shown beneath it.
            ->assertSee('What are your main goals?')
            ->assertSee('Better sleep');
    }

    public function test_an_answer_whose_question_was_retired_still_renders(): void
    {
        $lead = $this->leadWithQuizAnswers();
        $lead->update(['quiz_answers' => ['gone_away' => 'still here']]);

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->assertOk()
            ->assertSee('gone_away')
            ->assertSee('still here');
    }

    public function test_the_consent_audit_relation_manager_renders(): void
    {
        $lead = Lead::factory()->create();

        LeadConsent::create([
            'lead_id' => $lead->getKey(),
            'channel' => 'email',
            'granted' => true,
            'consent_text' => 'Email me my protocol plan.',
            'source' => 'quiz',
            'ip_address' => '203.0.113.9',
            'consented_at' => now(),
        ]);

        Livewire::test(ConsentsRelationManager::class, [
            'ownerRecord' => $lead,
            'pageClass' => EditLead::class,
        ])
            ->assertOk()
            ->assertSee('Email me my protocol plan.')
            ->assertSee('203.0.113.9');
    }

    public function test_the_lead_form_cannot_change_a_consent(): void
    {
        // IT COULD, AND THAT WAS THE HOLE. The form's toggles wrote the cached
        // booleans and no audit row, so an operator entering somebody's opt-out
        // changed a column nothing downstream reads while the audit — which
        // everything downstream does read — still said granted. The next
        // workflow run subscribed them at the destination, and reported success.
        //
        // The phone is explicit because `->tel()` validates the whole form on
        // save and the factory's random number does not always satisfy it — a
        // test that fails on the dice rather than on the code is worse than none.
        $lead = Lead::factory()->create(['phone' => '+15555550123']);
        app(RecordConsentAction::class)->execute($lead, 'email', true, source: 'quiz');

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->fillForm(['email_consent' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($lead->fresh()->email_consent);
        $this->assertSame(1, LeadConsent::query()->where('lead_id', $lead->getKey())->count());
    }

    public function test_an_operator_records_a_withdrawal_as_a_new_audit_entry(): void
    {
        // The path that had to exist for the docs to be true: nothing in the
        // admin could record a withdrawal at all. It APPENDS — the grant stays
        // on the record, because "they agreed, then changed their mind" is two
        // facts and an audit that overwrote the first would lose one of them.
        $lead = Lead::factory()->create();
        app(RecordConsentAction::class)->execute($lead, 'email', true, source: 'quiz');

        Livewire::test(ConsentsRelationManager::class, [
            'ownerRecord' => $lead,
            'pageClass' => EditLead::class,
        ])
            ->callTableAction('record', data: [
                'channel' => 'email',
                'decision' => 'withdrew',
                'consent_text' => 'Asked to be removed by email.',
            ])
            ->assertHasNoActionErrors();

        $rows = LeadConsent::query()->where('lead_id', $lead->getKey())->orderBy('id')->get();

        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]->granted);
        $this->assertFalse($rows[1]->granted);
        $this->assertSame('admin', $rows[1]->source);
        $this->assertNotNull($rows[1]->recorded_by_user_id);

        // And it reaches the thing that decides whether they may be marketed to.
        $this->assertFalse(app(ConsentResolver::class)->resolve($lead->fresh())->grants('email'));
    }

    private function leadWithQuizAnswers(): Lead
    {
        $quiz = Quiz::create(['slug' => 'intake', 'name' => 'Intake', 'is_active' => true]);
        $step = QuizStep::create(['quiz_id' => $quiz->id, 'slug' => 'goals', 'name' => 'Goals', 'position' => 1]);

        QuizQuestion::create([
            'quiz_step_id' => $step->id,
            'slug' => 'main_goals',
            'kind' => 'text',
            'prompt' => 'What are your main goals?',
            'position' => 1,
        ]);

        return Lead::factory()->create([
            'quiz_id' => $quiz->id,
            'quiz_answers' => ['main_goals' => 'Better sleep'],
        ]);
    }
}
