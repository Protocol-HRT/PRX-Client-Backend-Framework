<?php

namespace App\Services\Quiz;

use App\Cms\Support\VisibleWhen;
use App\Enums\Quiz\QuizQuestionKind;
use App\Models\Quiz\Quiz;
use App\Models\Quiz\QuizQuestion;
use Illuminate\Support\Collection;

/**
 * Checks a submitted answer set against the quiz that produced it.
 *
 * THE BROWSER'S EVALUATION IS A RENDERING DECISION; THIS ONE IS THE RULE. The
 * walker hides a question whose conditions fail, but a submission is just an
 * HTTP request and can carry anything. Re-evaluating here is what makes
 * `visible_when` a constraint rather than a suggestion — otherwise a caller
 * could answer a question that was never asked, and the report would quote a
 * measurement the visitor never gave.
 *
 * Answers are evaluated IN QUIZ ORDER, because a condition can only reference
 * an earlier question: visibility is resolved against the answers accepted so
 * far, not against the whole submission. Evaluating against everything would
 * let two questions make each other visible.
 *
 * Unknown keys are DROPPED rather than rejected. A quiz edited between a
 * visitor loading the page and submitting it is normal, not an attack, and
 * failing their submission because a question was retired mid-session loses a
 * real lead over a stale key.
 */
class QuizAnswerValidator
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array{answers: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(Quiz $quiz, array $answers): array
    {
        $questions = QuizQuestion::query()
            ->where('quiz_id', $quiz->id)
            ->active()
            ->with(['step', 'options' => fn ($q) => $q->active()])
            ->get()
            // A single composite key rather than sortBy()'s multi-comparison
            // array, which expects [column, direction] pairs and does not do
            // what it looks like it does with a list of closures. Ordering is
            // not cosmetic here: a condition can only reference an EARLIER
            // answer, so getting it wrong silently drops every conditional
            // question — which is exactly what it did.
            ->sortBy(fn (QuizQuestion $q): string => sprintf(
                '%06d-%06d-%06d',
                $q->step?->position ?? 0,
                $q->position,
                $q->id,
            ));

        $accepted = [];
        $errors = [];

        foreach ($questions as $question) {
            if ($question->kind === QuizQuestionKind::Contact) {
                // Contact becomes the lead's own columns and is validated by
                // the lead request, not here.
                continue;
            }

            $get = fn (string $field): mixed => $accepted[$field] ?? null;

            $stepVisible = VisibleWhen::passes($question->step?->visible_when ?? [], $get);
            $visible = $stepVisible && VisibleWhen::passes($question->visible_when ?? [], $get);

            if (! $visible) {
                // Silently dropped, not an error: the visitor branched away
                // and any value here is stale state, not a lie.
                continue;
            }

            $value = $answers[$question->slug] ?? null;

            if ($this->isBlank($value)) {
                if ($question->is_required) {
                    $errors["quiz_answers.{$question->slug}"] = "{$question->prompt} is required.";
                }

                continue;
            }

            $error = $this->checkValue($question, $value);

            if ($error !== null) {
                $errors["quiz_answers.{$question->slug}"] = $error;

                continue;
            }

            $accepted[$question->slug] = $this->normalise($question, $value);
        }

        return ['answers' => $accepted, 'errors' => $errors];
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function checkValue(QuizQuestion $question, mixed $value): ?string
    {
        $allowed = $this->allowedValues($question);

        return match ($question->kind) {
            QuizQuestionKind::SingleSelect => $allowed !== null && ! in_array((string) $value, $allowed, true)
                ? 'That is not one of the available answers.'
                : null,

            QuizQuestionKind::MultiSelect, QuizQuestionKind::HealthGoals => $this->checkMulti($value, $allowed),

            QuizQuestionKind::Age, QuizQuestionKind::Scale, QuizQuestionKind::Measurement => is_numeric($value)
                ? $this->checkBounds($question, (float) $value)
                : 'Expected a number.',

            QuizQuestionKind::Sex => is_string($value) ? null : 'Expected a value.',

            default => is_scalar($value) ? null : 'Expected a single value.',
        };
    }

    private function checkMulti(mixed $value, ?array $allowed): ?string
    {
        if (! is_array($value)) {
            return 'Expected a list of answers.';
        }

        if ($allowed === null) {
            return null;
        }

        foreach ($value as $item) {
            if (! in_array((string) $item, $allowed, true)) {
                return 'That is not one of the available answers.';
            }
        }

        return null;
    }

    /**
     * Bounds come from the question's own config, so a slider cannot be
     * submitted past the range an operator set. Absent bounds mean unbounded
     * rather than zero — a config nobody filled in must not reject everything.
     */
    private function checkBounds(QuizQuestion $question, float $value): ?string
    {
        $config = $question->config ?? [];

        $min = $config['min'] ?? $config['min_cm'] ?? $config['min_kg'] ?? null;
        $max = $config['max'] ?? $config['max_cm'] ?? $config['max_kg'] ?? null;

        if ($min !== null && $value < (float) $min) {
            return "Must be at least {$min}.";
        }

        if ($max !== null && $value > (float) $max) {
            return "Must be at most {$max}.";
        }

        return null;
    }

    /**
     * The values this question actually offers, or null when it does not
     * constrain them (a free-text or numeric answer).
     */
    private function allowedValues(QuizQuestion $question): ?array
    {
        if ($question->kind === QuizQuestionKind::HealthGoals) {
            return \App\Models\Kb\HealthGoal::query()->forQuiz()->pluck('slug')->all();
        }

        if (! $question->kind->usesAuthoredOptions()) {
            return null;
        }

        return $question->options->pluck('value')->map(fn ($v): string => (string) $v)->all();
    }

    /** Multi answers are de-duplicated and re-indexed so the stored JSON is a list. */
    private function normalise(QuizQuestion $question, mixed $value): mixed
    {
        if (is_array($value)) {
            return Collection::make($value)->map(fn ($v) => is_scalar($v) ? $v : null)
                ->filter(fn ($v) => $v !== null)
                ->unique()
                ->values()
                ->all();
        }

        return $value;
    }
}
