<?php

namespace App\Integrations;

use App\Enums\Privacy\DataClassification;
use App\Models\Integrations\IntegrationInstance;
use App\Models\Quiz\QuizQuestion;
use App\Workflows\WorkflowContext;
use App\Workflows\WorkflowRegistry;
use RuntimeException;

/**
 * Decides what actually leaves this system, and refuses when it should not.
 *
 * ─── The failure this exists to prevent ────────────────────────────────
 *
 * Once "push to a CRM" is a dropdown beside a list of fields, mapping a quiz's
 * health answers into a destination that must never receive them is one click,
 * looks exactly like every correct mapping, and produces no error. Nothing
 * downstream can catch it: the driver sees a string, the vendor accepts it, and
 * the run log records a success. So the check has to happen here, between the
 * operator's mapping and the driver, and it has to be structural rather than a
 * warning somebody dismissed once.
 *
 * ─── Three outcomes, not two ───────────────────────────────────────────
 *
 * `block` and `send` alone would force operators to choose between a broken
 * funnel and an unsafe one, and the unsafe one always wins. `redact` is the
 * third: the mapping runs, the destination learns the field was present, and the
 * value never leaves. That is genuinely useful — "answered the sleep question"
 * is often all a marketing flow needs, and it is not health data.
 *
 * ─── Why permission is a setting, not a rule in this file ──────────────
 *
 * Whether a destination may receive health data is a fact about the operator's
 * contracts, not about our code. An install with a signed BAA is entitled to
 * send it; hardcoding a vendor's name into a refusal here would be wrong for
 * them and would go stale the moment that vendor changed its terms. So this
 * compares two declared facts — the field's classification and the instance's
 * attestation — and has no opinion of its own about any vendor.
 *
 * @see DataClassification
 * @see IntegrationInstance::attestPhi()
 */
class FieldMap
{
    /** Send the value as it is. */
    public const ON_PHI_SEND = 'send';

    /** Send a marker that the field was present, but not its value. */
    public const ON_PHI_REDACT = 'redact';

    /** Refuse the whole push. The default, because it is the safe direction. */
    public const ON_PHI_BLOCK = 'block';

    /** What a redacted value becomes at the far end. */
    public const REDACTED = '[redacted]';

    public function __construct(private readonly WorkflowRegistry $registry) {}

    /**
     * Resolve one mapping against a context, or throw if it must not be sent.
     *
     * @param  list<array{source: string, destination: string, on_phi?: string}>  $mappings
     * @return array<string, scalar|null>
     */
    public function apply(array $mappings, WorkflowContext $context, IntegrationInstance $instance): array
    {
        $resolved = [];

        foreach ($mappings as $mapping) {
            $source = $mapping['source'] ?? null;
            $destination = $mapping['destination'] ?? null;

            if (! is_string($source) || ! is_string($destination) || $source === '' || $destination === '') {
                continue;
            }

            $classification = $this->classify($source, $context);

            // RE-CHECKED AT RUN TIME, not only when the form was saved. An
            // instance's PHI attestation can be revoked after a workflow was
            // authored — that is the whole point of allowing revocation — and a
            // check that only ran at authoring time would keep shipping data the
            // operator has since withdrawn permission for.
            if ($classification === DataClassification::Phi && ! $instance->phi_permitted) {
                $behaviour = $mapping['on_phi'] ?? self::ON_PHI_BLOCK;

                if ($behaviour === self::ON_PHI_BLOCK) {
                    throw new RuntimeException(
                        "Refused: [{$source}] is health data and [{$instance->name}] is not marked as "
                        .'permitted to receive it. Either attest that you have an agreement covering '
                        .'health data with this provider, set this field to redact, or remove it from '
                        .'the mapping.'
                    );
                }

                if ($behaviour === self::ON_PHI_REDACT) {
                    $resolved[$destination] = self::REDACTED;

                    continue;
                }

                // ON_PHI_SEND is a deliberate override and is allowed — the
                // operator may be relying on something this system cannot see.
                // It leaves no trace outside the workflow's own config: the
                // attestation history records who permitted health data to
                // reach the destination, never who overrode a single mapping.
            }

            $resolved[$destination] = $this->scalar($this->read($source, $context));
        }

        return $resolved;
    }

    /**
     * Read a source's value.
     *
     * `WorkflowContext::get()` deliberately bounds reads to the subject's
     * allow-list and returns null for anything else — which is correct for
     * conditions, and would silently blank every quiz answer here. Answers are
     * not allow-listed columns; they are per-question values inside one JSON
     * column, gated by `classify()` above rather than by the allow-list. So they
     * are read directly, and everything else still goes through the bounded path.
     */
    private function read(string $source, WorkflowContext $context): mixed
    {
        if (! str_starts_with($source, 'quiz_answers.')) {
            return $context->get($source);
        }

        $answers = $context->subject?->getAttribute('quiz_answers');

        return is_array($answers) ? ($answers[substr($source, 13)] ?? null) : null;
    }

    /**
     * How sensitive a mappable source is.
     *
     * Two vocabularies meet here. A bare name is a registered subject field and
     * is classified by the allow-list; `quiz_answers.{slug}` is one authored
     * question and is classified by that question, because a quiz's questions are
     * operator content and their sensitivity is not knowable from the column they
     * happen to land in.
     *
     * ANYTHING ELSE IS TREATED AS HEALTH DATA. Failing closed is the only safe
     * default for a check whose job is to prevent a leak: a source nobody has
     * classified is precisely the one not to wave through.
     */
    public function classify(string $source, WorkflowContext $context): DataClassification
    {
        if (str_starts_with($source, 'quiz_answers.')) {
            return $this->classifyQuizAnswer(substr($source, 13), $context);
        }

        if ($context->subjectKey === null) {
            return DataClassification::Phi;
        }

        return $this->registry->classificationFor($context->subjectKey, $source);
    }

    private function classifyQuizAnswer(string $slug, WorkflowContext $context): DataClassification
    {
        $quizId = $context->subject?->getAttribute('quiz_id');

        $question = QuizQuestion::query()
            ->when($quizId !== null, fn ($query) => $query->where('quiz_id', $quizId))
            ->where('slug', $slug)
            ->first();

        // A slug with no question behind it — renamed, deleted, mistyped — is
        // unclassifiable, so it is treated as the most sensitive thing it could
        // be rather than the least.
        return $question?->effectiveDataClass() ?? DataClassification::Phi;
    }

    /**
     * Everything a destination could be given, with its classification.
     *
     * Powers the mapper's source picker, and it is the same list the check above
     * uses — so the form cannot offer a field the runtime would refuse to
     * classify.
     *
     * @return array<string, array{label: string, class: DataClassification}>
     */
    public function sourcesFor(?string $subjectKey, ?int $quizId = null): array
    {
        if ($subjectKey === null) {
            return [];
        }

        $sources = [];

        foreach ($this->registry->fieldsFor($subjectKey) as $field => $label) {
            $sources[$field] = [
                'label' => $label,
                'class' => $this->registry->classificationFor($subjectKey, $field),
            ];
        }

        foreach (QuizQuestion::query()->when($quizId !== null, fn ($q) => $q->where('quiz_id', $quizId))->get() as $question) {
            $sources['quiz_answers.'.$question->slug] = [
                'label' => 'Quiz — '.strip_tags((string) $question->prompt),
                'class' => $question->effectiveDataClass(),
            ];
        }

        return $sources;
    }

    /** @return scalar|null */
    private function scalar(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        // An array or object reaching a destination as "Array" is worse than
        // nothing, so it is encoded rather than cast.
        return json_encode($value);
    }
}
