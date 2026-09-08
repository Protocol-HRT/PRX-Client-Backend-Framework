<?php

namespace App\Filament\Concerns;

use App\Models\Referral\ReferralSource;
use App\Models\User;

/**
 * THE ONE PLACE a dashboard decides whose numbers it is showing.
 *
 * The same widgets serve the staff admin and the partner portal — a national
 * group, a regional group and a solo affiliate all read one dashboard, shaped by
 * scope. Two implementations would drift, and the one an affiliate sees is the
 * one nobody checks.
 *
 * `null` means unscoped (staff). `[]` means an empty scope, and the difference is
 * load-bearing: a partner whose organisation was deleted must see NOTHING, never
 * everything. Every consumer must preserve that distinction — see
 * ReferralMetrics::funnelForIds().
 */
trait ResolvesReferralScope
{
    /**
     * @return list<int>|null
     */
    protected function referralScopeIds(): ?array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            // No identity resolves to no rows. A dashboard that defaults to
            // "everything" when it cannot tell who is asking is how a portal
            // leaks on a session edge case.
            return [];
        }

        return $user->visibleReferralSourceIds();
    }

    /** The organisation whose dashboard this is, or null for staff. */
    protected function referralViewer(): ?ReferralSource
    {
        $user = auth()->user();

        return $user instanceof User ? $user->referralSource : null;
    }

    protected function viewingAsPartner(): bool
    {
        return $this->referralScopeIds() !== null;
    }
}
