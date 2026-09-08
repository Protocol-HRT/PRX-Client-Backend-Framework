<?php

namespace App\Actions\Referral;

use App\Models\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralLink;
use Illuminate\Support\Str;

/**
 * Bind a referral to a lead at capture time.
 *
 * FIRST ATTRIBUTION WINS AND IS NEVER OVERWRITTEN. Commissions are money, so a
 * later touch may not silently reassign an existing credit — that is the same
 * property that made lead dedup a grouping problem rather than a merge, and the
 * same reason `referral_clicks` is append-only. A lead that already carries a
 * `referral_code` is returned untouched.
 *
 * The lead is bound to the CLICK, not just the code, so the landing that produced
 * it — its real landing_url, its utm values, its timestamp — is recoverable from
 * one join. Binding only the code would leave "which of this affiliate's four
 * campaigns produced the sale" unanswerable.
 *
 * The visitor's own `utm_*` / referrer / landing_url are taken FROM THE CLICK,
 * which is the fix for the real defect: the storefront reads those at
 * quiz-SUBMIT time (so navigating lost them) and the checkout path never sent
 * them at all. The click holds what was captured at the actual landing.
 */
class AttributeLeadAction
{
    public function execute(Lead $lead, ?string $code, ?string $visitorId = null): Lead
    {
        if ($lead->referral_code !== null) {
            return $lead;
        }

        // Normalise through the one shared helper — lowercase then bound. It
        // returns null for anything that cannot be a real code, and null means
        // "no referral", never an error: this runs after the lead is already
        // persisted and may not throw.
        $code = ReferralLink::normalizeCode($code);

        if ($code === null) {
            return $lead;
        }

        // Prefer this visitor's own click; fall back to the code alone so a
        // referral still lands when the cookie was lost between landing and
        // conversion (a different device, cleared storage, a shared link).
        $click = null;

        if ($visitorId !== null && Str::isUuid($visitorId)) {
            $click = ReferralClick::where('code', $code)
                ->where('visitor_id', $visitorId)
                ->latest('clicked_at')
                ->first();
        }

        $link = ReferralLink::resolve($code);

        // An unresolvable code is still recorded on the lead. It is evidence in
        // the same way an unmatched click is: someone arrived claiming it.
        $lead->forceFill([
            'referral_code' => $code,
            'referral_click_id' => $click?->id,
            'referral_link_id' => $link?->id ?? $click?->referral_link_id,
            'referral_source_id' => $link?->referral_source_id ?? $click?->referral_source_id,
            'attributed_at' => now(),
        ]);

        // THE CLICK WINS OVER WHAT THE FORM SENT, and that is the point rather
        // than a tie-break. The storefront's own `attribution()` reads the URL at
        // SUBMIT time, so on the quiz path `landing_url` is the quiz page and the
        // utm values are whatever survived to the last navigation — the exact
        // defect this feature exists to fix. The click holds what was captured at
        // the actual landing, so where the two disagree the click is simply the
        // correct one.
        if ($click) {
            // THE UTM TUPLE IS REPLACED AS A SET, not merged field by field. A
            // click carrying only `utm_source=flyer` against a lead submitted
            // from `/quiz?utm_source=google&utm_medium=cpc` would otherwise store
            // `flyer / cpc` — a source and medium that never occurred together,
            // which is worse than either source alone because it looks real.
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $field) {
                $lead->{$field} = $click->{$field};
            }

            // These two are independent of the utm tuple, so they are taken only
            // when the click actually has them.
            foreach (['referrer', 'landing_url'] as $field) {
                if (filled($click->{$field})) {
                    $lead->{$field} = $click->{$field};
                }
            }
        }

        $lead->save();

        return $lead;
    }
}
