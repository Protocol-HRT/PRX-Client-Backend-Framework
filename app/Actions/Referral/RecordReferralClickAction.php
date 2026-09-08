<?php

namespace App\Actions\Referral;

use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralLink;
use Illuminate\Support\Str;

/**
 * Record one referral arrival.
 *
 * ALWAYS WRITES A ROW, even for a code that resolves to nothing. An unknown,
 * expired, retired or mistyped code is the case someone asks about later — "my
 * flyer sent 400 people and I was paid for none" is answerable from these rows and
 * unanswerable if we drop them. `referral_link_id` / `referral_source_id` are then
 * null while `code` still holds what the visitor actually arrived with.
 *
 * IT IS ALSO IDEMPOTENT PER VISITOR PER CODE. The storefront's middleware runs on
 * every request, so without this a single visitor refreshing a landing page would
 * mint arbitrarily many clicks and inflate a commission. First arrival wins and
 * later ones return the original row — which is also what makes a unique click
 * derivable by COUNT(DISTINCT visitor_id) rather than stored.
 */
class RecordReferralClickAction
{
    /**
     * @param  array<string, string|null>  $context  landing_url, referrer, ip_address,
     *                                               user_agent and any utm_* values.
     */
    public function execute(string $code, string $visitorId, array $context = []): ?ReferralClick
    {
        $code = ReferralLink::normalizeCode($code);

        if ($code === null || ! Str::isUuid($visitorId)) {
            // A visitor id we did not mint is not a visitor id. Refusing here
            // keeps the uniqueness count honest — a caller supplying junk would
            // otherwise get one "unique visitor" per request.
            return null;
        }

        $link = ReferralLink::resolve($code);

        // firstOrCreate against the (code, visitor_id) UNIQUE index rather than a
        // check-then-insert: two concurrent first landings would otherwise both
        // pass the check and write two rows, inflating a raw click count. The
        // index is what makes idempotency structural instead of best-effort.
        return ReferralClick::firstOrCreate([
            'code' => $code,
            'visitor_id' => $visitorId,
        ], [
            'referral_link_id' => $link?->id,
            'referral_source_id' => $link?->referral_source_id,
            // Snapshotted so the row still names the source after it is deleted.
            'source_slug' => $link?->source?->slug,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => Str::limit((string) ($context['user_agent'] ?? ''), 500, '') ?: null,
            'referrer' => Str::limit((string) ($context['referrer'] ?? ''), 2040, '') ?: null,
            'landing_url' => Str::limit((string) ($context['landing_url'] ?? ''), 2040, '') ?: null,
            'utm_source' => $context['utm_source'] ?? null,
            'utm_medium' => $context['utm_medium'] ?? null,
            'utm_campaign' => $context['utm_campaign'] ?? null,
            'utm_term' => $context['utm_term'] ?? null,
            'utm_content' => $context['utm_content'] ?? null,
            'clicked_at' => now(),
        ]);
    }
}
