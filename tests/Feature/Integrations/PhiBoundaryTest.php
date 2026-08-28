<?php

namespace Tests\Feature\Integrations;

use App\Enums\Privacy\DataClassification;
use App\Enums\Quiz\QuizQuestionKind;
use App\Integrations\Contracts\SyncsContacts;
use App\Integrations\FieldMap;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Messages\ContactPayload;
use App\Models\Integrations\IntegrationInstance;
use App\Models\Lead;
use App\Models\Quiz\Quiz;
use App\Models\Quiz\QuizQuestion;
use App\Models\Quiz\QuizStep;
use App\Models\User;
use App\Workflows\Actions\PushToIntegrationAction;
use App\Workflows\WorkflowContext;
use App\Workflows\WorkflowRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The boundary that decides what health data may leave this system.
 *
 * WHAT MAKES THIS WORTH TESTING CAREFULLY is that every failure here is silent.
 * A field mapped to the wrong destination produces no error, no warning and a
 * successful-looking run row; the driver sees a string and the vendor accepts
 * it. There is no downstream check that could catch it, and no log that would
 * show it afterwards. So the tests below are about REFUSALS — the cases where
 * nothing happening is the correct outcome — and about the defaults that apply
 * when nobody has classified anything.
 */
class PhiBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_data_is_refused_by_a_destination_that_is_not_permitted(): void
    {
        $lead = Lead::factory()->create(['gender' => 'male']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/health data/');

        $this->map()->apply(
            [['source' => 'gender', 'destination' => 'sex']],
            $this->context($lead),
            $this->destination(phiPermitted: false),
        );
    }

    public function test_the_same_field_is_allowed_once_the_operator_has_attested(): void
    {
        // The permission is a SETTING, not a hardcoded refusal: an install with
        // an agreement covering health data is entitled to send it, and code
        // that refused regardless would simply be wrong for them.
        $lead = Lead::factory()->create(['gender' => 'male']);

        $sent = $this->map()->apply(
            [['source' => 'gender', 'destination' => 'sex']],
            $this->context($lead),
            $this->destination(phiPermitted: true),
        );

        $this->assertSame(['sex' => 'male'], $sent);
    }

    public function test_redaction_sends_the_shape_without_the_value(): void
    {
        // The middle option, and the reason there are three outcomes rather than
        // two: block-or-send forces a choice between a broken funnel and an
        // unsafe one, and the unsafe one always wins.
        $lead = Lead::factory()->create(['gender' => 'male']);

        $sent = $this->map()->apply(
            [['source' => 'gender', 'destination' => 'sex', 'on_phi' => FieldMap::ON_PHI_REDACT]],
            $this->context($lead),
            $this->destination(phiPermitted: false),
        );

        $this->assertSame(['sex' => FieldMap::REDACTED], $sent);
        $this->assertStringNotContainsString('male', json_encode($sent));
    }

    public function test_blocking_is_the_default_when_the_mapping_says_nothing(): void
    {
        // An operator who never opened the dropdown must get the safe direction.
        $lead = Lead::factory()->create(['age' => 44]);

        $this->expectException(RuntimeException::class);

        $this->map()->apply(
            [['source' => 'age', 'destination' => 'age']],
            $this->context($lead),
            $this->destination(phiPermitted: false),
        );
    }

    public function test_ordinary_fields_are_unaffected(): void
    {
        // The gate must not become a reason nothing works. Attribution data is
        // not personal and has to flow freely.
        $lead = Lead::factory()->create(['utm_source' => 'newsletter', 'email' => 'a@example.invalid']);

        $sent = $this->map()->apply(
            [
                ['source' => 'utm_source', 'destination' => 'source'],
                ['source' => 'email', 'destination' => 'email'],
            ],
            $this->context($lead),
            $this->destination(phiPermitted: false),
        );

        $this->assertSame(['source' => 'newsletter', 'email' => 'a@example.invalid'], $sent);
    }

    public function test_an_unregistered_source_is_treated_as_health_data(): void
    {
        // FAILS CLOSED. A field nobody classified is exactly the one not to wave
        // through — and `ip_address` is a real column deliberately left off the
        // allow-list, so this also pins that the allow-list still bounds the
        // mapper as it bounds conditions.
        $lead = Lead::factory()->create();

        $this->assertSame(
            DataClassification::Phi,
            $this->map()->classify('ip_address', $this->context($lead)),
        );
    }

    public function test_a_quiz_answer_inherits_its_questions_kind(): void
    {
        // Nobody classified this question by hand. A measurement exists so a
        // report can compute BMI, which is a clinical measure whatever the
        // fields are called, and the default has to say so.
        $lead = $this->leadWithQuiz(kind: QuizQuestionKind::Measurement, slug: 'body', dataClass: null);

        $this->assertSame(
            DataClassification::Phi,
            $this->map()->classify('quiz_answers.body', $this->context($lead)),
        );
    }

    public function test_health_goals_are_not_health_data_and_may_leave_the_building(): void
    {
        // THE OPERATOR'S DETERMINATION, 2026-08-28, and the one reserved kind
        // that is not `Phi`: a goal is aspirational — "more energy" — not a
        // condition, a diagnosis or a treatment. It is also the answer the whole
        // quiz exists to collect, so a default that blocked it would have to be
        // downgraded by hand on every install.
        //
        // The assertion that matters is the second one: the goal reaches an
        // UNATTESTED destination. Classifying it `Sensitive` and stopping there
        // would prove nothing, because `Sensitive` gates nothing.
        $lead = $this->leadWithQuiz(
            kind: QuizQuestionKind::HealthGoals,
            slug: 'goals',
            dataClass: null,
            answers: ['goals' => 'more-energy'],
        );

        $this->assertSame(
            DataClassification::Sensitive,
            $this->map()->classify('quiz_answers.goals', $this->context($lead)),
        );

        $sent = $this->map()->apply(
            [['source' => 'quiz_answers.goals', 'destination' => 'Goals']],
            $this->context($lead),
            $this->destination(phiPermitted: false),
        );

        $this->assertSame(['Goals' => 'more-energy'], $sent);
    }

    public function test_a_clinical_question_beside_a_goal_is_still_refused(): void
    {
        // The downgrade is per KIND, not a hole in the gate. "Anything to flag?"
        // is an authored question — blood pressure, cholesterol, blood sugar,
        // liver — and authored questions still default to health data.
        $lead = $this->leadWithQuiz(kind: QuizQuestionKind::MultiSelect, slug: 'flags', dataClass: null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/health data/');

        $this->map()->apply(
            [['source' => 'quiz_answers.flags', 'destination' => 'Flags']],
            $this->context($lead),
            $this->destination(phiPermitted: false),
        );
    }

    public function test_an_operator_can_classify_one_question_by_hand(): void
    {
        // The column overrides the kind's default, because only the operator
        // knows what their own free-text question asks for.
        $lead = $this->leadWithQuiz(
            kind: QuizQuestionKind::Text,
            slug: 'referrer',
            dataClass: DataClassification::General,
        );

        $this->assertSame(
            DataClassification::General,
            $this->map()->classify('quiz_answers.referrer', $this->context($lead)),
        );
    }

    public function test_an_authored_question_defaults_to_health_data_and_is_actually_blocked(): void
    {
        // THE REGRESSION. This test previously asserted `Sensitive` and called
        // that "protective" — but only `Phi` engages the gate, so `Sensitive`
        // was indistinguishable from `General` at the one moment that matters.
        // On this install that left a question offering "blood pressure,
        // cholesterol, blood sugar, liver" free to leave for an unattested
        // destination, labelled "personal" in the mapper.
        //
        // So this asserts the classification AND that it actually stops a push.
        // The first half alone is what let the hole hide.
        $lead = $this->leadWithQuiz(kind: QuizQuestionKind::MultiSelect, slug: 'flags', dataClass: null);
        $lead->update(['quiz_answers' => ['flags' => ['blood_pressure']]]);

        $this->assertSame(
            DataClassification::Phi,
            $this->map()->classify('quiz_answers.flags', $this->context($lead->fresh())),
        );

        $this->expectException(RuntimeException::class);
        $this->map()->apply(
            [['source' => 'quiz_answers.flags', 'destination' => 'flags']],
            $this->context($lead->fresh()),
            $this->destination(phiPermitted: false),
        );
    }

    public function test_an_operator_can_downgrade_a_question_that_is_not_clinical(): void
    {
        // The protective default is only tolerable because the downgrade is
        // reachable — it is a Sensitivity select on the question, not a database
        // write. "How did you hear about us?" is a real question in a real quiz.
        $lead = $this->leadWithQuiz(
            kind: QuizQuestionKind::SingleSelect,
            slug: 'how-heard',
            dataClass: DataClassification::General,
        );
        $lead->update(['quiz_answers' => ['how-heard' => 'a podcast']]);

        $sent = $this->map()->apply(
            [['source' => 'quiz_answers.how-heard', 'destination' => 'source']],
            $this->context($lead->fresh()),
            $this->destination(phiPermitted: false),
        );

        $this->assertSame(['source' => 'a podcast'], $sent);
    }

    public function test_an_answer_with_no_question_behind_it_is_treated_as_health_data(): void
    {
        // A renamed, deleted or mistyped slug is unclassifiable, so it gets the
        // most sensitive reading rather than the least.
        $lead = Lead::factory()->create();

        $this->assertSame(
            DataClassification::Phi,
            $this->map()->classify('quiz_answers.does-not-exist', $this->context($lead)),
        );
    }

    public function test_a_quiz_answer_is_readable_even_though_it_is_not_allow_listed(): void
    {
        // Answers are not columns; they live inside one JSON column and are
        // gated per question rather than by the subject allow-list. Reading them
        // through the bounded accessor would blank every one of them silently.
        $lead = $this->leadWithQuiz(kind: QuizQuestionKind::Text, slug: 'referrer', dataClass: DataClassification::General);
        $lead->update(['quiz_answers' => ['referrer' => 'a friend']]);

        $sent = $this->map()->apply(
            [['source' => 'quiz_answers.referrer', 'destination' => 'how_heard']],
            $this->context($lead->fresh()),
            $this->destination(phiPermitted: false),
        );

        $this->assertSame(['how_heard' => 'a friend'], $sent);
    }

    public function test_a_revoked_attestation_stops_the_next_push(): void
    {
        // THE REASON THE CHECK RUNS AT SEND TIME AND NOT ONLY AT SAVE TIME. A
        // workflow authored while permission existed must stop working the
        // moment permission is withdrawn.
        $lead = Lead::factory()->create(['gender' => 'male']);
        $instance = $this->destination(phiPermitted: false);
        $instance->attestPhi(true, 'Agreement signed');

        $mapping = [['source' => 'gender', 'destination' => 'sex']];

        $this->assertSame(
            ['sex' => 'male'],
            $this->map()->apply($mapping, $this->context($lead), $instance->fresh()),
        );

        $instance->attestPhi(false, 'Agreement lapsed');

        $this->expectException(RuntimeException::class);
        $this->map()->apply($mapping, $this->context($lead), $instance->fresh());
    }

    public function test_an_attestation_records_who_said_so_and_survives_being_reversed(): void
    {
        // The flag is an ATTESTATION, not a verification — so who and when is the
        // whole value of it, and a pair of columns would lose the previous
        // answer the second time it was toggled.
        $user = User::factory()->create();
        $instance = $this->destination(phiPermitted: false);

        $instance->attestPhi(true, 'BAA signed 2026-08-01', $user);
        $instance->attestPhi(false, 'Contract ended', $user);

        $this->assertFalse($instance->fresh()->phi_permitted);
        $this->assertSame(2, $instance->attestations()->count());

        $history = $instance->attestations()->get();
        $this->assertSame([false, true], $history->pluck('permitted')->all());
        $this->assertSame($user->id, $history->first()->attested_by_user_id);

        // "Revoked" and "never attested" are different facts and must not
        // collapse into one another.
        $this->assertSame('Contract ended', $history->first()->note);
    }

    public function test_an_attestation_cannot_be_edited_away(): void
    {
        $instance = $this->destination(phiPermitted: false);
        $attestation = $instance->attestPhi(true, 'Original claim');

        $this->expectException(RuntimeException::class);
        $attestation->update(['note' => 'Something more convenient']);
    }

    public function test_identity_fields_go_through_the_gate_rather_than_round_it(): void
    {
        // A contact needs something to be identified by, so email and phone are
        // sent even when the operator mapped nothing. The tempting shortcut is to
        // read them straight off the subject — which would be a hole by
        // construction, harmless today only because this install happens to
        // classify email as personal rather than health data.
        //
        // This pins that they arrive as MAPPINGS. Reclassify `email` as PHI and
        // the push must refuse, exactly as it would for any other health field.
        app(WorkflowRegistry::class)->registerSubject(
            'lead',
            Lead::class,
            'Lead',
            ['email' => ['label' => 'Email', 'class' => DataClassification::Phi]],
        );

        app(IntegrationRegistry::class)
            ->registerProvider('fake_crm', ContactSyncingDriver::class, 'Fake CRM');

        $lead = Lead::factory()->create(['email' => 'someone@example.invalid']);

        $instance = IntegrationInstance::create([
            'name' => 'Marketing platform',
            'provider' => 'fake_crm',
            'capabilities' => ['crm'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/health data/');

        app(PushToIntegrationAction::class)->handle(
            $this->context($lead),
            ['integration' => $instance->slug, 'operation' => 'sync_contact'],
        );
    }

    private function map(): FieldMap
    {
        return app(FieldMap::class);
    }

    private function context(Lead $lead): WorkflowContext
    {
        return new WorkflowContext(
            triggerType: 'model_updated',
            triggerTarget: 'lead',
            subject: $lead,
            subjectKey: 'lead',
        );
    }

    private function destination(bool $phiPermitted): IntegrationInstance
    {
        $instance = IntegrationInstance::create([
            'name' => 'Marketing platform',
            'provider' => 'local_mail',
            'capabilities' => ['crm'],
        ]);

        // Through the real path, not by setting the column. `phi_permitted` is
        // no longer mass-assignable precisely so a permission cannot exist
        // without an attestation behind it — and a test that reached around that
        // would be testing a state production cannot produce.
        if ($phiPermitted) {
            $instance->attestPhi(true, 'Test attestation');
        }

        return $instance;
    }

    /** @param  array<string, mixed>  $answers */
    private function leadWithQuiz(QuizQuestionKind $kind, string $slug, ?DataClassification $dataClass, array $answers = []): Lead
    {
        $quiz = Quiz::create(['name' => 'Intake', 'slug' => 'intake', 'is_active' => true]);
        $step = QuizStep::create(['quiz_id' => $quiz->id, 'slug' => 'step-1', 'name' => 'Step', 'position' => 1]);

        QuizQuestion::create([
            'quiz_step_id' => $step->id,
            'slug' => $slug,
            'kind' => $kind,
            'data_class' => $dataClass,
            'prompt' => 'A question',
            'is_required' => false,
            'position' => 1,
            'is_active' => true,
        ]);

        return Lead::factory()->create(['quiz_id' => $quiz->id, 'quiz_answers' => $answers]);
    }
}

/** Minimal CRM-shaped driver, so the push reaches the field map. */
class ContactSyncingDriver implements SyncsContacts
{
    public function test(IntegrationInstance $instance): void {}

    public function upsertContact(IntegrationInstance $instance, ContactPayload $contact): string
    {
        return 'remote-phi-test';
    }

    public function addToGroup(IntegrationInstance $instance, string $remoteId, string $group, ContactPayload $contact): void {}
}
