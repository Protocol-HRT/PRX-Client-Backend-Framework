<?php

namespace App\Http\Resources\Api\V1\Catalog\Concerns;

use App\Enums\BillingPeriod;
use Illuminate\Support\Collection;

/**
 * The one implementation of what a package costs.
 *
 * Extracted when the relation rails were found mispricing packages: they
 * substituted a package with its default plan, so a $399 buy-once stack
 * advertised the plan's $279.99 on every upsell and pairs-with card. The
 * substitution predates packages having price columns at all — see the
 * migration that added them — and the docblock asserting "packages carry no
 * price columns of their own" stayed true-looking long after it stopped being
 * true. Two implementations of a pricing rule is how that happens; there is
 * now one.
 *
 * NOTE there is still a second range implementation outside this file, in
 * QuizSchemaBuilder::priceRange(), which computes live rather than reading a
 * resource. The two must move together. Folding it in here is worth doing and
 * is not this change.
 */
trait BuildsPackagePricing
{
    /** What a single purchase of the package itself costs, sale winning. */
    private function packageEffectivePrice(?float $sale, ?float $retail): ?float
    {
        $effective = $sale ?? $retail;

        return $effective !== null ? (float) $effective : null;
    }

    /**
     * The span a visitor could pay, across every way of buying this.
     *
     * SPANS PLANS **AND** THE PACKAGE'S OWN PRICE. Usually the plans decide
     * it, because a subscription is discounted against a one-off purchase — so
     * the obvious implementation reads plans alone. That silently produces a
     * wrong "from" the moment a single purchase is discounted below the
     * cheapest plan, which is exactly what a sale on the package is for.
     *
     * Only PRICED candidates count: a plan with neither retail nor sale, or a
     * package with no price of its own, contributes nothing rather than a zero
     * that would drag `from` to 0.00.
     *
     * @param  Collection<int, mixed>  $plans
     * @return array{from: float|null, to: float|null, currency: string}
     */
    private function packagePriceRange(Collection $plans, ?float $ownEffective): array
    {
        $prices = $plans
            ->filter(fn ($p) => $p->sale_price !== null || $p->retail_price !== null)
            ->map(fn ($p) => (float) ($p->sale_price ?? $p->retail_price))
            ->values();

        if ($ownEffective !== null) {
            $prices->push($ownEffective);
        }

        return [
            'from' => $prices->isNotEmpty() ? round((float) $prices->min(), 2) : null,
            'to' => $prices->isNotEmpty() ? round((float) $prices->max(), 2) : null,
            'currency' => 'USD',
        ];
    }

    /**
     * The single "From $X" figure a listing card leads with, and the cadence
     * that figure is actually charged at.
     *
     * WHY THIS IS NOT `price_range` WITH THE TOP END DROPPED. The range spans
     * every way of buying, and those ways are not priced in the same unit: on
     * this install every package's cheapest offer is a monthly rate and its
     * dearest is a multi-month PREPAY TOTAL, so the honest span reads
     * "$279.99 - $1,259.96" and a visitor sees a stack that might cost $1,259.96
     * a month. `price_range` is still correct for what it measures and is still
     * emitted; a card just cannot show two numbers in two units side by side.
     *
     * So: the lowest MONTHLY-cadence price, carrying its OWN suffix rather than
     * a "/mo" this method invents. A plan's cadence is structural
     * (`billing_period`, an enum) and trustworthy. A package's own price has no
     * cadence column at all — only the free-text `price_suffix` an operator
     * typed — so it is always a candidate and its suffix is passed through
     * verbatim. Nothing here fabricates a unit for a number that has none.
     *
     * Intro prices are excluded deliberately: `intro_price` buys one billing
     * cycle, so leading a card with it advertises a number the visitor pays
     * once. The detail page's plan picker is where that offer belongs.
     *
     * The fallback matters more than it looks. A package sold only as a
     * 6-month prepay has no monthly price to lead with, and rendering nothing
     * would hide a purchasable stack; it falls back to the cheapest price of
     * any cadence, with that price's suffix, so the card still says something
     * true.
     *
     * `plan_id` NAMES THE PLAN THE FIGURE CAME FROM, or null when the package's
     * OWN price won. A card that quotes "From $279.99/mo" and then adds a
     * different price to the cart has lied at the last possible moment, and the
     * alternative — having the frontend search the plans for one whose price
     * matches the string it rendered — is reverse-engineering an answer this
     * method already knows. Null is meaningful rather than missing: it says
     * "buy the package itself", which the cart supports.
     *
     * ON AN EQUAL PRICE, THE NON-RECURRING OPTION WINS. This is not a tidiness
     * rule, it decides what the buyer is signed up to. A package is a set group
     * of products bought ONCE; a plan lays a monthly or prepaid REBILL over the
     * same bundle. Downstream, prescribe-rx reads a package with no plan id as a
     * single transaction and a plan carrying a recurring subscription as a
     * subscription — and on a local checkout the same choice decides whether the
     * merchant account starts auto-billing. So when a plan and the package's own
     * price are the same number, choosing the plan silently enrols someone in a
     * recurring commitment at a price they could have paid once.
     *
     * Live example this was written for: Metabolic Reset, own price 399, plan #4
     * monthly retail 499 / sale 399 — an exact tie. Every published plan on this
     * install is `is_recurring`, so the own price is currently the only
     * non-recurring path; the flag is read rather than assumed so a future
     * one-off plan behaves correctly without another change here.
     *
     * CAVEAT, AND IT IS A SCHEMA GAP RATHER THAN A RULE: "no plan means no
     * rebill" holds here only because `packages` carries NO recurring column at
     * all — every billing field (`is_recurring`, `billing_mode`,
     * `rebill_strategy`, `trial_days`, `billing_period`, `term_months`) lives on
     * `plans`. prescribe-rx models recurrence on the PACKAGE as well as the
     * plan, so a recurring package with no plan is a shape that exists over
     * there and cannot be represented here. If a package-level recurring flag is
     * ever added, this tie-break must read it — `plan_id: null` would no longer
     * be sufficient evidence of a single transaction.
     *
     * A genuinely CHEAPER recurring plan still wins — this breaks ties, it does
     * not prefer one-time purchases over better prices.
     *
     * @param  Collection<int, mixed>  $plans
     * @return array{amount: float|null, suffix: string|null, plan_id: int|null, currency: string}
     */
    private function packagePriceFrom(Collection $plans, ?float $ownEffective, ?string $ownSuffix): array
    {
        $priced = $plans->filter(fn ($p) => $p->sale_price !== null || $p->retail_price !== null);

        $candidate = fn ($p) => [
            'plan_id' => $p->id,
            'recurring' => (bool) $p->is_recurring,
            'amount' => (float) ($p->sale_price ?? $p->retail_price),
            // An operator's authored suffix wins over the cadence's default, so
            // "/month" instead of "/mo" stays the operator's call.
            'suffix' => $p->price_suffix ?: ($p->billing_period instanceof BillingPeriod
                ? $p->billing_period->suffix()
                : null),
        ];

        $monthly = $priced
            ->filter(fn ($p) => $p->billing_period === BillingPeriod::Monthly)
            ->map($candidate)
            ->values();

        if ($ownEffective !== null) {
            // The package's own price belongs to no plan, and buying the
            // package alone never creates a rebill.
            $monthly->push([
                'plan_id' => null,
                'recurring' => false,
                'amount' => $ownEffective,
                'suffix' => $ownSuffix,
            ]);
        }

        $pool = $monthly->isNotEmpty() ? $monthly : $priced->map($candidate)->values();

        if ($pool->isEmpty()) {
            return ['amount' => null, 'suffix' => null, 'plan_id' => null, 'currency' => 'USD'];
        }

        // Amount decides; `recurring` only breaks a tie (false sorts before
        // true). See the note above on why that tie is a commercial choice.
        $cheapest = $pool->sortBy([
            ['amount', 'asc'],
            ['recurring', 'asc'],
        ])->first();

        return [
            'amount' => round((float) $cheapest['amount'], 2),
            'suffix' => $cheapest['suffix'],
            'plan_id' => $cheapest['plan_id'],
            'currency' => 'USD',
        ];
    }
}
