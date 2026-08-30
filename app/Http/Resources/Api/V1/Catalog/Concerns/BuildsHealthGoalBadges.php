<?php

namespace App\Http\Resources\Api\V1\Catalog\Concerns;

use App\Models\Catalog\Package;
use App\Models\Kb\HealthGoal;
use Illuminate\Support\Collection;

/**
 * The health-goal badges a catalog item shows — "weight loss",
 * "inflammation support", what the thing is GOOD FOR.
 *
 * One vocabulary, deliberately: these are the same `health_goals` rows the
 * quiz asks about. A second tag table would let the quiz and the storefront
 * name the same goal differently, which is the one outcome worth ruling out.
 *
 * `badge_color` is a palette NAME. The frontend resolves it through
 * `--palette-{name}` and derives the label from `--palette-{name}-contrast`,
 * so there is no text colour here and adding one is a decision, not a
 * convenience — a badge cannot currently be authored unreadable.
 */
trait BuildsHealthGoalBadges
{
    /**
     * @return list<array{name: string, slug: string, badge_color: string|null}>
     */
    private function healthGoalBadges(Collection $goals): array
    {
        return $goals
            ->map(fn (HealthGoal $goal): array => [
                'name' => $goal->name,
                'slug' => $goal->slug,
                'badge_color' => $goal->badge_color,
            ])
            ->values()
            ->all();
    }

    /**
     * A package's badges: its own if it has any, otherwise the union of the
     * goals of the products inside it.
     *
     * Derived by default so tagging a product once updates every stack
     * containing it, and a stack can never claim a goal its contents do not
     * treat. The override REPLACES rather than augments: a stack marketed for
     * one goal has to be able to show that goal alone.
     *
     * Returns [] rather than guessing when neither relation is loaded — a
     * caller that forgot the eager load gets no badges, not N+1 queries.
     *
     * @return list<array{name: string, slug: string, badge_color: string|null}>
     */
    private function packageHealthGoalBadges(Package $package): array
    {
        if ($package->relationLoaded('healthGoals') && $package->healthGoals->isNotEmpty()) {
            return $this->healthGoalBadges($package->healthGoals);
        }

        // Deliberately NOT products(): that relation is unconstrained and is
        // what the resource serializes. See Package::healthGoalSourceProducts().
        if (! $package->relationLoaded('healthGoalSourceProducts')) {
            return [];
        }

        $derived = $package->healthGoalSourceProducts
            ->filter(fn ($product) => $product->relationLoaded('healthGoals'))
            ->flatMap(fn ($product) => $product->healthGoals)
            ->unique('id')
            ->values();

        return $this->healthGoalBadges($derived);
    }
}
