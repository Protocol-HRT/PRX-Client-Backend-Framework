<?php

namespace App\Services\Recommendations;

use App\Models\Catalog\Ingredient;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Kb\HealthGoal;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves a health goal into what this visitor may actually be offered.
 *
 * The chain is the one the health-goals migration established:
 *
 *     goal --> ingredient --> product --> package/stack
 *              (weighted)     (contains)  (contains)
 *
 * This class adds one hop to it: **eligibility is applied at the ingredient,
 * before products are materialised at all.** That ordering is the point.
 * Filtering products afterwards would work, but it would mean loading the
 * products behind an ingredient a woman must never be offered, and every later
 * addition (a PDF generator, an email, an LLM protocol writer) would have to
 * remember to filter again. Gating at the first hop means an ineligible
 * substance never enters the result set for anything downstream to leak.
 *
 * ELIGIBILITY IS NOT RANKING. `relevance_weight` orders options that are all
 * acceptable; `SexEligibility` and the age bounds decide what is acceptable at
 * all. They are separate passes and the gate runs first — a weight of 100 on
 * testosterone must not float it past a female visitor.
 *
 * A PRODUCT WITH NO INGREDIENTS IS INELIGIBLE, not unrestricted. It cannot be
 * reached through the chain anyway (there is no ingredient to match a goal),
 * so stating it costs nothing — but it closes the hole where an unclassified
 * product becomes a bypass around the whole gate. Today exactly one product is
 * in that state and it is `testosterone-cypionate`, which is precisely the
 * item the sex rule exists to catch. That is not a coincidence to rely on; it
 * is a reason the rule is written down.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: decide what to show when the result is
 * empty. A woman choosing "Sexual Wellness" against today's catalogue gets
 * nothing, because both mapped ingredients are male-only and no female
 * alternative is stocked. That is the correct answer and the resolver returns
 * it honestly rather than falling back to something unrelated. Presenting an
 * empty protocol is the funnel's job, and it should still capture the lead.
 */
class GoalRecommendationResolver
{
    /**
     * Ingredients serving this goal that the visitor may be offered, ranked.
     *
     * `is_first_line` outranks `relevance_weight` — it is the operator saying
     * "this is the default answer whatever the numbers say", so a weight can
     * never overtake it. Both are pivot columns, so the sort happens after
     * hydration rather than in SQL; these sets are a handful of rows.
     */
    public function ingredientsFor(HealthGoal $goal, VisitorProfile $profile): Collection
    {
        return $goal->ingredients()
            ->where('ingredients.is_active', true)
            ->eligibleFor($profile->sex, $profile->age)
            ->get()
            ->sortByDesc(fn (Ingredient $i): array => [
                (int) $i->pivot->is_first_line,
                (int) $i->pivot->relevance_weight,
            ])
            ->values();
    }

    /**
     * Published products reachable from this goal for this visitor.
     *
     * A product inherits the rank of the BEST-ranked eligible ingredient it
     * contains, not the sum: containing two mid-ranked ingredients does not
     * make a product a better answer than one containing the first-line one.
     *
     * Note what is NOT re-checked here — a product's other ingredients. A
     * product surfaces because it holds an eligible ingredient, and if it also
     * holds an ineligible one it should not be offered. See `strictProductsFor`
     * for that reading. This method exists because the two differ and the
     * difference is a clinical judgement, not a default.
     */
    public function productsFor(HealthGoal $goal, VisitorProfile $profile): Collection
    {
        $ingredients = $this->ingredientsFor($goal, $profile);

        if ($ingredients->isEmpty()) {
            // An ELOQUENT collection, not `collect()`. The controller calls
            // ->loadMissing() on whatever comes back, and a base
            // Support\Collection has no such method — so returning collect()
            // here threw the instant a goal resolved to nothing, which is
            // exactly the restricted case this whole feature exists to
            // produce. Caught by driving the funnel in a browser; the unit
            // tests missed it because they call the resolver directly.
            return new Collection();
        }

        $rankByIngredient = $ingredients->mapWithKeys(fn (Ingredient $i, int $index): array => [
            $i->id => $ingredients->count() - $index,
        ]);

        return Product::query()
            ->published()
            ->whereHas('ingredients', fn ($q) => $q->whereIn('ingredients.id', $ingredients->pluck('id')))
            ->with('ingredients')
            ->get()
            ->map(function (Product $product) use ($rankByIngredient): Product {
                $product->recommendation_rank = $product->ingredients
                    ->map(fn (Ingredient $i): int => $rankByIngredient[$i->id] ?? 0)
                    ->max() ?? 0;

                return $product;
            })
            ->sortByDesc('recommendation_rank')
            ->values();
    }

    /**
     * The conservative reading: a product is offered only when EVERY
     * ingredient it contains is eligible for this visitor.
     *
     * This is the safe default for anything that reads as medical advice — a
     * generated protocol, a PDF, an email — because a combination product
     * containing one male-only ingredient is a male-only product however good
     * its other components are. `productsFor` is the permissive reading and
     * suits browsing surfaces where the visitor is choosing for themselves.
     *
     * A product with no ingredients at all is excluded by both, deliberately.
     */
    public function strictProductsFor(HealthGoal $goal, VisitorProfile $profile): Collection
    {
        return $this->productsFor($goal, $profile)
            ->filter(fn (Product $product): bool => $product->ingredients->isNotEmpty()
                && $product->ingredients->every(
                    fn (Ingredient $i): bool => $i->permits($profile->sex, $profile->age)
                ))
            ->values();
    }

    /**
     * Whether a product is SAFE for this visitor — independent of any goal.
     *
     * Safety and relevance are different questions and conflating them is a
     * live bug rather than a nicety: a stack holding a weight-loss product and
     * a general wellness product is perfectly safe, but the wellness product
     * is not mapped to the weight goal. Judging the stack by goal-relevance
     * rejected every package in the catalogue. This asks only the safety half.
     *
     * A product with no ingredients is not safe to RECOMMEND — nothing
     * classifies it, so nothing can vouch for it.
     */
    public function productIsSafe(Product $product, VisitorProfile $profile): bool
    {
        $ingredients = $product->relationLoaded('ingredients')
            ? $product->ingredients
            : $product->ingredients()->get();

        return $ingredients->isNotEmpty()
            && $ingredients->every(fn (Ingredient $i): bool => $i->permits($profile->sex, $profile->age));
    }

    /**
     * Stacks surfaced through their contents.
     *
     * A package is never mapped to a goal directly — the migration's rule — so
     * it appears because it holds a product RELEVANT to the goal. It is then
     * offered only if every product it holds is SAFE, because a stack is
     * bought whole and the visitor cannot decline the one item in it they must
     * not take. Relevance qualifies one product; safety must hold for all of
     * them.
     */
    public function packagesFor(HealthGoal $goal, VisitorProfile $profile): Collection
    {
        $relevant = $this->strictProductsFor($goal, $profile)->pluck('id');

        if ($relevant->isEmpty()) {
            return new Collection();
        }

        return Package::query()
            ->published()
            ->whereHas('products', fn ($q) => $q->whereIn('products.id', $relevant))
            ->with('products.ingredients')
            ->get()
            ->filter(fn (Package $package): bool => $package->products->isNotEmpty()
                && $package->products->every(
                    fn (Product $p): bool => $this->productIsSafe($p, $profile)
                ))
            ->values();
    }

    /**
     * Everything a protocol needs for one goal, in one call.
     *
     * `outcome` names the three states the funnel must tell apart, because
     * two of them render zero products and need completely different copy:
     *
     *   matched    - something is suitable.
     *   restricted - something exists; this visitor may not have it.
     *   unmapped   - nobody has built this goal out yet.
     *
     * Telling `restricted` from `unmapped` cannot be done from
     * `excluded_count` alone. That counts INGREDIENT-level exclusions only,
     * and a goal can restrict at the PRODUCT level instead: map a unisex
     * ingredient A, stock one product holding both A and male-only B, and a
     * female visitor gets an eligible ingredient, an excluded_count of 0, and
     * no products. Reading the count would call that "unmapped" and tell her
     * we have not built this out — when the truth is that we have, and it is
     * not for her.
     *
     * So the comparison is against an UNFILTERED baseline: what would this
     * goal offer someone we know nothing about? Empty baseline means nobody
     * built it; non-empty baseline with an empty result means this visitor was
     * filtered out. The extra resolve is over a handful of rows and only runs
     * when the result is already empty.
     */
    public function resolve(HealthGoal $goal, VisitorProfile $profile): array
    {
        $mapped = $goal->ingredients()->where('ingredients.is_active', true)->count();
        $ingredients = $this->ingredientsFor($goal, $profile);
        $products = $this->strictProductsFor($goal, $profile);

        $outcome = 'matched';

        if ($products->isEmpty()) {
            $baseline = $profile->isEmpty()
                ? $products
                : $this->strictProductsFor($goal, new VisitorProfile());

            $outcome = $baseline->isEmpty() ? 'unmapped' : 'restricted';
        }

        return [
            'goal' => $goal,
            'ingredients' => $ingredients,
            'products' => $products,
            'packages' => $this->packagesFor($goal, $profile),
            'mapped_count' => $mapped,
            'excluded_count' => $mapped - $ingredients->count(),
            'outcome' => $outcome,
        ];
    }
}
