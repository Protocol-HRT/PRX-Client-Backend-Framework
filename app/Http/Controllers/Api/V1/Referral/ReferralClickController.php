<?php

namespace App\Http\Controllers\Api\V1\Referral;

use App\Actions\Referral\RecordReferralClickAction;
use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralClickController extends ApiController
{
    /**
     * Record a referral arrival.
     *
     * POST /api/v1/referrals/clicks
     *
     * CALLED BY THE STOREFRONT'S EDGE MIDDLEWARE, not by page JavaScript. That
     * matters for the audit trail: the middleware runs on the Next.js server, so
     * the visitor's real address arrives via X-Forwarded-For and is trusted here
     * because TRUSTED_PROXIES already names that host. A browser-side call would
     * be trivially forgeable by anyone wanting to inflate a commission.
     *
     * ALWAYS 200, EVEN FOR A CODE THAT MATCHES NOTHING. This is fire-and-forget on
     * a visitor's very first page view; a 404 here would teach the middleware to
     * retry or log noise on every mistyped link, and the row is written either way
     * so the arrival is still auditable.
     *
     * ANONYMOUS AND UNAUTHENTICATED TODAY, which bounds what it can be trusted
     * for: anyone can post here with a fresh random `visitor_id` per request and
     * mint clicks, throttle and proxies permitting. What that CANNOT be forged is
     * the IP, which arrives via the storefront's forwarded header from a trusted
     * peer. So this is safe while commissions are paid on CONVERSIONS — leads,
     * which require a real checkout — and it is not a safe basis for paying
     * per-click. When item 43 step 2 lands, this is the strongest candidate for
     * `auth:sanctum` plus an ability, with the throttle keyed on the forwarded
     * address rather than the shared client id.
     *
     * @unauthenticated
     */
    public function store(Request $request, RecordReferralClickAction $action): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'visitor_id' => ['required', 'uuid'],
            'landing_url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
        ]);

        $click = $action->execute(
            $validated['code'],
            $validated['visitor_id'],
            [
                'landing_url' => $validated['landing_url'] ?? null,
                'referrer' => $validated['referrer'] ?? null,
                'utm_source' => $validated['utm_source'] ?? null,
                'utm_medium' => $validated['utm_medium'] ?? null,
                'utm_campaign' => $validated['utm_campaign'] ?? null,
                'utm_term' => $validated['utm_term'] ?? null,
                'utm_content' => $validated['utm_content'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        // REPORTS NOTHING ABOUT THE CODE. An earlier version returned whether it
        // resolved to a live source, which no caller ever read and which handed
        // an anonymous prober one bit per request — enough to enumerate a live
        // affiliate roster from a public endpoint. `recorded` is only whether the
        // request was well-formed enough to store.
        return $this->success(['recorded' => $click !== null]);
    }
}
