<?php

namespace App\Services\Quiz;

use App\Enums\CatalogStatus;
use App\Enums\Quiz\QuizQuestionKind;
use App\Models\Catalog\Plan;
use App\Models\Kb\HealthGoal;
use App\Models\Quiz\Quiz;
use App\Models\Quiz\QuizQuestion;
use App\Models\Quiz\QuizQuestionOption;
use App\Models\Quiz\QuizStep;
use Illuminate\Support\Facades\Storage;

/**
 * Turns an authored quiz into the payload the frontend walker renders.
 *
 * Two jobs the frontend must not do for itself:
 *
 * 1. **Resolve reserved kinds.** `health_goals` reads the goals table rather
 *    than authored options, so adding or withdrawing a goal changes the quiz
 *    with no edit here. A frontend fetching goals separately would work, but
 *    it would have to know WHICH questions need that second call — the walker
 *    stays dumb precisely because everything arrives resolved.
 *
 * 2. **Compute price ranges.** An option may say where its price comes from
 *    (`products`, `packages:protocol`, `packages:stack`); the range is
 *    computed here from live plan prices and never stored. A price authored
 *    into an option goes stale the moment a plan changes, silently, next to a
 *    buying decision.
 *
 * `visible_when` is passed through UNRESOLVED, deliberately: the conditions
 * describe branching over answers that do not exist yet, so they can only be
 * evaluated in the browser as the visitor answers — and again on the server at
 * submit, against what they actually sent. Two evaluations of one rule set,
 * which is why the rule format is shared rather than reimplemented.
 */
class QuizSchemaBuilder
{
    public function build(Quiz $quiz): array
    {
        $quiz->load([
            'steps' => fn ($q) => $q->active()->orderBy('position'),
            'steps.questions' => fn ($q) => $q->active()->orderBy('position'),
            'steps.questions.options' => fn ($q) => $q->active()->orderBy('position'),
        ]);

        $steps = $quiz->steps->map(fn (QuizStep $step): array => [
            'slug' => $step->slug,
            'name' => $step->name,
            'heading' => $step->heading,
            'description' => $step->description,
            'visible_when' => $step->visible_when ?? [],
            'questions' => $step->questions->map(fn (QuizQuestion $q): array => $this->question($q))->all(),
        ])->all();

        return [
            'slug' => $quiz->slug,
            'name' => $quiz->name,
            'steps' => $steps,
            // A flat list so the walker can check completeness without
            // re-walking the tree, mirroring the provider's clinical intake
            // schema (`required_slugs`) rather than inventing a second shape.
            'required_slugs' => collect($steps)
                ->flatMap(fn (array $s): array => $s['questions'])
                ->filter(fn (array $q): bool => $q['is_required'])
                ->pluck('slug')
                ->values()
                ->all(),
        ];
    }

    private function question(QuizQuestion $question): array
    {
        return [
            'slug' => $question->slug,
            'kind' => $question->kind->value,
            'prompt' => $question->prompt,
            'help' => $question->help,
            'is_required' => $question->is_required,
            'visible_when' => $question->visible_when ?? [],
            'config' => $question->config ?? [],
            'options' => $this->options($question),
        ];
    }

    /**
     * Options as the visitor will see them — authored for the select kinds,
     * resolved from their source for the reserved ones.
     */
    private function options(QuizQuestion $question): array
    {
        if ($question->kind === QuizQuestionKind::HealthGoals) {
            return HealthGoal::query()
                ->forQuiz()
                ->orderBy('position')
                ->orderBy('name')
                ->get()
                ->map(fn (HealthGoal $goal): array => [
                    'value' => $goal->slug,
                    // The outcome-framed line, falling back to the label —
                    // the same rule HealthGoalResource applies, so the quiz
                    // and any other consumer read a goal identically.
                    'label' => $goal->prompt ?: $goal->name,
                    'description' => $goal->description,
                    'icon' => $goal->icon,
                    'is_exclusive' => false,
                    'price_range' => null,
                    'image_url' => $goal->image_path ? Storage::disk('public')->url($goal->image_path) : null,
                ])
                ->all();
        }

        if (! $question->kind->usesAuthoredOptions()) {
            return [];
        }

        return $question->options
            ->map(fn (QuizQuestionOption $option): array => [
                'value' => $option->value,
                'label' => $option->label,
                'description' => $option->description,
                'icon' => $option->icon,
                'is_exclusive' => $option->is_exclusive,
                'price_range' => $this->priceRange($option->price_source),
                'image_url' => null,
            ])
            ->all();
    }

    /**
     * Live min/max across published plans for whatever the option points at.
     *
     * Returns null rather than a zero range when nothing is priced: a card
     * reading "$0 – $0" is worse than a card with no price on it, and the
     * frontend can only tell the two apart if one of them is absent.
     */
    private function priceRange(?string $source): ?array
    {
        if ($source === null || $source === '') {
            return null;
        }

        $query = Plan::query()
            ->where('status', CatalogStatus::Published->value)
            ->where(fn ($q) => $q->whereNotNull('retail_price')->orWhereNotNull('sale_price'));

        if ($source === 'products') {
            $query->whereNotNull('product_id')
                ->whereHas('product', fn ($q) => $q->where('status', CatalogStatus::Published->value));
        } elseif (str_starts_with($source, 'packages')) {
            $tier = str_contains($source, ':') ? explode(':', $source, 2)[1] : null;

            $query->whereNotNull('package_id')
                ->whereHas('package', function ($q) use ($tier): void {
                    $q->where('status', CatalogStatus::Published->value);

                    // No tier in the source means "any package". A tier that
                    // matches nothing must yield NO range rather than every
                    // package's — an operator who mistypes a tier should see a
                    // missing price, not a wrong one.
                    if ($tier !== null && $tier !== '') {
                        $q->where('tier', $tier);
                    }
                });
        } else {
            return null;
        }

        $prices = $query->get(['retail_price', 'sale_price'])
            ->map(fn (Plan $p): float => (float) ($p->sale_price ?? $p->retail_price));

        if ($prices->isEmpty()) {
            return null;
        }

        return [
            'from' => round((float) $prices->min(), 2),
            'to' => round((float) $prices->max(), 2),
            'currency' => 'USD',
        ];
    }
}
