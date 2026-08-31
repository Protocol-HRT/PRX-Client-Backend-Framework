<?php

namespace App\Services\Recommendations;

use App\Enums\Quiz\QuizQuestionKind;
use App\Models\Lead;
use App\Models\Quiz\QuizQuestion;

/**
 * What a lead's stored quiz answers say about the visitor: which goals they
 * picked, and the two facts the eligibility gate runs on.
 *
 * RESOLVED BY QUESTION KIND, NEVER BY SLUG. `quiz_answers` is keyed by question
 * slug and a slug is operator-editable — renaming the "health_goals" question
 * in the admin would silently empty every report that matched on the string,
 * with no error anywhere. `Sex`, `Age` and `HealthGoals` are reserved cases of
 * QuizQuestionKind precisely because they read sources this install already
 * owns, which makes the kind the stable half of the pair and the slug the
 * volatile one. See QuizQuestionKind's own docblock.
 *
 * THE COLUMNS ARE THE FALLBACK, NOT THE SOURCE. `leads.gender` and `leads.age`
 * are populated by the CLINICAL intake at checkout; a marketing-quiz lead has
 * both null and carries its answers only inside `quiz_answers`. Reading the
 * columns alone — which is what `VisitorProfile::fromLead` does on its own —
 * therefore yields an empty profile for exactly the visitors this class exists
 * to serve, and an empty profile is PERMISSIVE: the eligibility gate would
 * quietly not run and a restricted visitor would be shown the full shelf. The
 * quiz answer wins, the column fills a gap, and neither is guessed.
 *
 * Absence is never an error. A lead created at checkout has no quiz at all,
 * and that is a normal shape rather than a broken one — it yields no goals and
 * whatever the columns hold.
 */
final readonly class QuizProfile
{
    /**
     * @param  array<int, string>  $goals  Health-goal slugs, in the order answered.
     */
    public function __construct(
        public array $goals,
        public VisitorProfile $visitor,
    ) {}

    public static function fromLead(Lead $lead): self
    {
        $answers = is_array($lead->quiz_answers) ? $lead->quiz_answers : [];
        $slugs = self::reservedSlugs($lead);

        // One column read per field, and only where the quiz did not answer.
        $columns = VisitorProfile::fromLead($lead);

        $sex = self::string($answers, $slugs[QuizQuestionKind::Sex->value] ?? null);
        $age = self::int($answers, $slugs[QuizQuestionKind::Age->value] ?? null);

        return new self(
            goals: self::strings($answers, $slugs[QuizQuestionKind::HealthGoals->value] ?? null),
            visitor: new VisitorProfile(
                sex: $sex ?? $columns->sex,
                age: $age ?? $columns->age,
            ),
        );
    }

    /**
     * Slug of each reserved question on this lead's quiz, keyed by kind value.
     *
     * One query for all three rather than one per field. `quiz_id` is
     * denormalised onto the question by QuizQuestion::booted(), so this does
     * not need to walk steps. Ordered by id so a quiz that somehow carries two
     * questions of the same reserved kind resolves to the older one every
     * time, rather than to whichever the database happened to return first.
     *
     * @return array<string, string>
     */
    private static function reservedSlugs(Lead $lead): array
    {
        if ($lead->quiz_id === null) {
            return [];
        }

        $questions = QuizQuestion::query()
            ->where('quiz_id', $lead->quiz_id)
            ->whereIn('kind', [
                QuizQuestionKind::Sex->value,
                QuizQuestionKind::Age->value,
                QuizQuestionKind::HealthGoals->value,
            ])
            ->orderBy('id')
            ->get(['id', 'kind', 'slug']);

        $slugs = [];

        foreach ($questions as $question) {
            // First one wins — see the ordering note above.
            $slugs[$question->kind->value] ??= $question->slug;
        }

        return $slugs;
    }

    /** @return array<int, string> */
    private static function strings(array $answers, ?string $slug): array
    {
        $value = $slug === null ? null : ($answers[$slug] ?? null);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    private static function string(array $answers, ?string $slug): ?string
    {
        $value = $slug === null ? null : ($answers[$slug] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * An age answer arrives from a JSON column, so it may be an int or the
     * string form of one depending on what the browser sent. Anything that is
     * not a whole number is discarded rather than coerced — `(int) "adult"` is
     * 0, and 0 is a number the age gate would happily filter on.
     */
    private static function int(array $answers, ?string $slug): ?int
    {
        $value = $slug === null ? null : ($answers[$slug] ?? null);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
