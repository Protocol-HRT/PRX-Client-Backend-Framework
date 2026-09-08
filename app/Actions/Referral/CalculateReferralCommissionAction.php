<?php

namespace App\Actions\Referral;

use App\Enums\Payments\LeadPaymentStatus;
use App\Models\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralSource;
use Illuminate\Support\Facades\DB;

/**
 * Work out who is owed what on one conversion, and write it down.
 *
 * THE MODEL IS TELESCOPING OVERRIDE BANDS, which is the standard shape for a
 * sales hierarchy and the one prescribe-rx's commission engine also arrives at:
 *
 *   partner 10%, their group 15%, the national group 20%, on a $100 sale
 *   → cumulative 10 / 15 / 20  →  bands 10 / 5 / 5  →  $20 total
 *
 * Each tier earns the DIFFERENCE between its rate and the rate already claimed
 * beneath it, so the total paid out is the top rate in the chain and never the
 * sum of every rate. Get this wrong and a four-level tree pays 60% of revenue.
 *
 * A parent whose rate is LOWER than its child's earns a zero band rather than a
 * negative one — the cumulative figure is clamped to be non-decreasing going up.
 * That situation is a misconfiguration, but the answer to it is "you earn
 * nothing extra", never "the child owes you money".
 *
 * IDEMPOTENT. Keyed on (lead, source), so re-running after a correction updates
 * the bands in place instead of paying twice — but it refuses to touch a row
 * already marked paid, because that money has left.
 */
class CalculateReferralCommissionAction
{
    /**
     * @return list<ReferralCommission>
     */
    public function execute(Lead $lead): array
    {
        // Only a captured payment earns anything. An authorisation is not money.
        if ($lead->payment_status !== LeadPaymentStatus::Captured) {
            return [];
        }

        $basis = (float) ($lead->payment_amount ?? 0);

        if ($basis <= 0 || $lead->referral_source_id === null) {
            return [];
        }

        $source = ReferralSource::find($lead->referral_source_id);

        if (! $source) {
            return [];
        }

        // Nearest first: the earning source, then each ancestor. The ordering is
        // load-bearing — see ReferralSource::ancestorAndSelfIds() for why the
        // package's negative ancestor depth makes this easy to get backwards.
        $chain = ReferralSource::whereIn('id', $source->ancestorAndSelfIds())->get()
            ->keyBy('id');

        $written = [];
        $cumulative = 0.0;
        $tier = 0;

        foreach ($source->ancestorAndSelfIds() as $id) {
            $node = $chain->get($id);

            if (! $node) {
                continue;
            }

            $rate = (float) ($node->commission_rate ?? 0);

            // Non-decreasing going up: a parent can never claim less ground than
            // its own downline has already claimed.
            $next = max($cumulative, $rate);
            $band = round($next - $cumulative, 2);
            $cumulative = $next;

            if ($band > 0) {
                $written[] = $this->write($lead, $node, $tier, $basis, $band);
            }

            $tier++;
        }

        return $written;
    }

    private function write(Lead $lead, ReferralSource $node, int $tier, float $basis, float $rate): ReferralCommission
    {
        return DB::transaction(function () use ($lead, $node, $tier, $basis, $rate): ReferralCommission {
            $existing = ReferralCommission::where('lead_id', $lead->id)
                ->where('referral_source_id', $node->id)
                ->first();

            // Paid is final. Recomputing over it would either double-pay or
            // silently restate a settled figure; both are worse than a stale row
            // an operator can see and void by hand.
            if ($existing && $existing->status === ReferralCommission::STATUS_PAID) {
                return $existing;
            }

            $attributes = [
                'source_slug' => $node->slug,
                'source_name' => $node->name,
                'referral_code' => $lead->referral_code,
                'tier' => $tier,
                'basis_amount' => $basis,
                'rate' => $rate,
                'amount' => round($basis * $rate / 100, 2),
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return ReferralCommission::create($attributes + [
                'lead_id' => $lead->id,
                'referral_source_id' => $node->id,
                'status' => ReferralCommission::STATUS_PENDING,
            ]);
        });
    }
}
