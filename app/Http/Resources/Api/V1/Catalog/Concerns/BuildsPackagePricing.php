<?php

namespace App\Http\Resources\Api\V1\Catalog\Concerns;

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
}
