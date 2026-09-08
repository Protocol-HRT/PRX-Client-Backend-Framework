<?php

namespace App\Services\Referral;

use App\Enums\Payments\LeadPaymentStatus;
use App\Models\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralSource;
use Illuminate\Support\Carbon;

/**
 * The referral funnel: clicks → visitors → leads → conversions → revenue.
 *
 * ONE SERVICE FOR EVERY LEVEL, because the operator's requirement is that a
 * national group, a regional group and an individual partner all read the SAME
 * dashboard with the data shaped to their scope. Two implementations would drift,
 * and the one an affiliate sees is the one nobody checks.
 *
 * EVERY NUMBER IS ROLLED UP THROUGH THE TREE by default. This is the bug that
 * made the hierarchy look broken: clicks land on the LEAF that owns the code, so
 * a parent counting only its own rows shows zero however much its downline
 * produced. `rollup: false` gives a source's own direct numbers, which is only
 * meaningful next to the rolled-up figure.
 *
 * A CONVERSION IS A CAPTURED PAYMENT, not a lead and not an authorisation. Money
 * that was authorised and never captured is money nobody received, and paying
 * commission on it would be paying out of pocket.
 */
class ReferralMetrics
{
    /**
     * @return array{
     *     clicks:int, unique_visitors:int, leads:int,
     *     conversions:int, revenue:float, commission_owed:float,
     *     click_to_lead:float, lead_to_conversion:float
     * }
     */
    public function funnel(
        ReferralSource $source,
        bool $rollup = true,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        $ids = $rollup ? $source->descendantAndSelfIds() : [$source->id];

        return $this->funnelForIds($ids, $from, $to);
    }

    /**
     * The funnel over an explicit set of sources.
     *
     * NULL AND [] MEAN OPPOSITE THINGS, and the distinction is the whole safety
     * property of the partner dashboard:
     *   - `null` — no scope at all. Staff, seeing everything.
     *   - `[]`   — an empty scope. A partner whose organisation resolved to
     *              nothing, who must see NOTHING rather than everything.
     * A helper that collapsed the two would turn a deleted org into a full data
     * leak, which is the single most likely way this feature goes wrong.
     *
     * @param  list<int>|null  $ids
     */
    public function funnelForIds(?array $ids, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($ids === []) {
            return $this->empty();
        }

        $clicks = ReferralClick::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('referral_source_id', $ids))
            ->when($from, fn ($q) => $q->where('clicked_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('clicked_at', '<=', $to));

        // Cloned because the first ->count() would otherwise consume the builder
        // and the distinct count would be taken against a mutated query.
        $total = (clone $clicks)->count();
        $unique = (clone $clicks)->distinct('visitor_id')->count('visitor_id');

        $leads = Lead::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('referral_source_id', $ids))
            ->when($ids === null, fn ($q) => $q->whereNotNull('referral_source_id'))
            ->when($from, fn ($q) => $q->where('attributed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('attributed_at', '<=', $to));

        $leadCount = (clone $leads)->count();

        $converted = (clone $leads)->where('payment_status', LeadPaymentStatus::Captured->value);
        $conversions = (clone $converted)->count();
        $revenue = (float) (clone $converted)->sum('payment_amount');

        $commission = ReferralCommission::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('referral_source_id', $ids))
            ->outstanding()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->sum('amount');

        return [
            'clicks' => $total,
            'unique_visitors' => $unique,
            'leads' => $leadCount,
            'conversions' => $conversions,
            'revenue' => $revenue,
            'commission_owed' => (float) $commission,
            // Rates against UNIQUE visitors, not raw clicks: one person reloading
            // a landing page five times has not become five prospects, and a rate
            // that says otherwise flatters whoever refreshes most.
            'click_to_lead' => $unique > 0 ? round($leadCount / $unique * 100, 1) : 0.0,
            'lead_to_conversion' => $leadCount > 0 ? round($conversions / $leadCount * 100, 1) : 0.0,
        ];
    }

    /**
     * The same funnel, split into the rows a parent actually wants to see: one
     * per DIRECT child (each rolled up through its own subtree) plus this
     * source's own direct activity.
     *
     * Direct children rather than every descendant, because "Main Org 1 sees
     * aggregate broken down by their sub orgs" means one line per sub-org — a row
     * per leaf partner would be the same numbers at a useless altitude, and each
     * sub-org can drill in for that.
     *
     * NOTE: `ReferralBreakdownWidget` renders the same idea directly off a query
     * so the table can paginate and search. This method is the reference
     * implementation and what the tests pin; if the two ever disagree, the widget
     * is wrong.
     *
     * @return list<array{source:?ReferralSource, label:string, own:bool, metrics:array}>
     */
    public function breakdown(ReferralSource $source, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = [];

        foreach ($source->children()->orderBy('name')->get() as $child) {
            $rows[] = [
                'source' => $child,
                'label' => $child->name,
                'own' => false,
                'metrics' => $this->funnel($child, true, $from, $to),
            ];
        }

        // This source's OWN codes, listed last and labelled as direct. A group
        // that also runs its own campaigns would otherwise see that activity
        // vanish from a breakdown that only listed its children.
        $ownMetrics = $this->funnel($source, false, $from, $to);

        if ($rows === [] || $ownMetrics['clicks'] > 0 || $ownMetrics['leads'] > 0) {
            $rows[] = [
                'source' => $source,
                'label' => $source->name.' (direct)',
                'own' => true,
                'metrics' => $ownMetrics,
            ];
        }

        return $rows;
    }

    private function empty(): array
    {
        return [
            'clicks' => 0, 'unique_visitors' => 0, 'leads' => 0,
            'conversions' => 0, 'revenue' => 0.0, 'commission_owed' => 0.0,
            'click_to_lead' => 0.0, 'lead_to_conversion' => 0.0,
        ];
    }
}
