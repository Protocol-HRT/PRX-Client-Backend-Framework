<?php

namespace App\Enums\Quiz;

use App\Enums\Privacy\DataClassification;

/**
 * What a quiz question actually asks for, and therefore which control the
 * frontend renders and which rule validates the answer.
 *
 * TYPED KINDS RATHER THAN A GENERIC "SELECT", deliberately. A generic field
 * list would make the operator responsible for re-authoring things this
 * install already knows: the health goals are rows in `health_goals` with
 * their own ordering, icons and quiz visibility, and duplicating them as
 * options would let the two drift the first time a goal is withdrawn. The
 * reserved kinds below READ those sources instead, so there is one place a
 * goal exists.
 *
 * The same argument applies downward: `Measurement` carries its unit and
 * bounds in `config` rather than being three loose questions, because BMI is
 * computed from the pair and a height with no weight is not a partial answer,
 * it is an unusable one.
 *
 * `Contact` is the exception that proves the rule — it is a question kind so
 * an operator can position it, label it and set its consent copy, but its
 * answer is NOT stored in `quiz_answers`. It becomes the lead itself.
 */
enum QuizQuestionKind: string
{
    case SingleSelect = 'single_select';
    case MultiSelect = 'multi_select';
    case Scale = 'scale';
    case Measurement = 'measurement';
    case Text = 'text';

    // ── Reserved kinds: these read an existing source rather than options ──
    case Sex = 'sex';
    case Age = 'age';
    case HealthGoals = 'health_goals';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::SingleSelect => 'Single choice',
            self::MultiSelect => 'Multiple choice',
            self::Scale => 'Slider',
            self::Measurement => 'Measurement (height / weight)',
            self::Text => 'Free text',
            self::Sex => 'Sex — reserved',
            self::Age => 'Age — reserved',
            self::HealthGoals => 'Health goals — reserved',
            self::Contact => 'Contact details — reserved',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SingleSelect => 'One answer from a list you author. Renders as cards with an optional icon.',
            self::MultiSelect => 'Any number of answers. Mark one option "exclusive" (e.g. "None of these") to have it clear the rest.',
            self::Scale => 'A draggable number between two bounds you set.',
            self::Measurement => 'Height and weight together, so the report can compute BMI. Shown in feet/inches and pounds.',
            self::Text => 'A short typed answer.',
            self::Sex => 'Reads the eligibility buckets. Gates which treatments may be recommended, so it cannot be authored as free options.',
            self::Age => 'A slider. Feeds the same eligibility gate as sex.',
            self::HealthGoals => 'Reads Health Goals directly — add or withdraw a goal there and this question follows, with no edit here.',
            self::Contact => 'Name, email, phone and the consent toggles. Becomes the lead; it is not stored as an answer.',
        };
    }

    /** Kinds whose choices come from this table rather than an authored list. */
    public function usesAuthoredOptions(): bool
    {
        return in_array($this, [self::SingleSelect, self::MultiSelect], true);
    }

    /**
     * Kinds that read a source elsewhere in the install. The admin hides the
     * options repeater for these — an authored option would be ignored, and a
     * control that silently discards what an operator typed is worse than one
     * that is not offered.
     */
    public function isReserved(): bool
    {
        return in_array($this, [self::Sex, self::Age, self::HealthGoals, self::Contact], true);
    }

    /**
     * Whether the answer is stored in `leads.quiz_answers`.
     *
     * Contact is false: it becomes the lead's own columns, and copying an
     * email into a JSON blob as well would create a second address that can
     * disagree with the first.
     */
    public function isStoredAsAnswer(): bool
    {
        return $this !== self::Contact;
    }

    /**
     * How sensitive this kind of answer is, when nobody has said otherwise.
     *
     * A DEFAULT, NOT A RULE — `quiz_questions.data_class` overrides it, because
     * only the operator knows what their own free-text question asks for. What
     * this supplies is the answer for the majority of questions nobody will ever
     * classify by hand, and the reserved kinds where the answer is not a matter
     * of opinion: a health-goals question reads the health-goals table, and a
     * measurement exists so a report can compute BMI.
     *
     * THE DEFAULTS LEAN PROTECTIVE, and the asymmetry is the reason. An
     * over-classified field costs an operator one downgrade they had to think
     * about; an under-classified one sends health data somewhere it must not go,
     * silently, looking exactly like every correct mapping beside it.
     *
     * WHICH IS WHY AN AUTHORED QUESTION DEFAULTS TO `Phi`, not to something
     * milder. An earlier version of this method defaulted them to `Sensitive`,
     * which reads as cautious and is not: only `Phi` engages the field mapper's
     * gate, so `Sensitive` was indistinguishable from `General` at the only
     * moment that matters. On this very install that left "Anything to flag?"
     * — blood pressure, cholesterol, blood sugar, liver — and "On any
     * medications right now?" free to leave for an unattested destination,
     * labelled "personal" in the mapper.
     *
     * The questions an operator authors in a health quiz are the ones nobody
     * else can classify in advance, so the default has to be the safe end and
     * the downgrade has to be deliberate. "How did you hear about us?" is a
     * perfectly reasonable thing to mark `General`; it is just not something
     * this enum can know.
     *
     * `Contact` is Sensitive rather than Phi on purpose: a name and an email
     * address are personal, but they disclose nothing clinical on their own —
     * and classifying them as health data would make ordinary mail impossible
     * for an install that never touches PHI at all.
     */
    public function defaultDataClassification(): DataClassification
    {
        return match ($this) {
            // Reads the health goals table. There is no reading of this that is
            // not clinical.
            self::HealthGoals => DataClassification::Phi,
            // Height and weight exist here so a report can compute BMI, which is
            // a clinical measure whatever the fields are called.
            self::Measurement => DataClassification::Phi,
            // Sex and age gate which treatments may be recommended, so in this
            // context they carry the inference even though elsewhere they would
            // be ordinary demographics.
            self::Sex, self::Age => DataClassification::Phi,
            // Identity, not clinical content.
            self::Contact => DataClassification::Sensitive,
            // Authored questions. Health data until an operator says otherwise —
            // see above; this is the half that was wrong.
            self::SingleSelect, self::MultiSelect, self::Scale, self::Text => DataClassification::Phi,
        };
    }

    /** @return array<string, string> value => label, for a Filament select. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
