<?php

namespace App\Filament\Partner\Resources\Organizations\RelationManagers;

use App\Filament\Resources\Referrals\RelationManagers\LinksRelationManager;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The Codes panel, on the partner portal.
 *
 * The shared manager renders here today only because `ReferralLink` has no
 * policy, and Filament's non-strict mode allows what it cannot find a rule for.
 * That is an accident one `shield:generate` away from becoming a 403, so the rule
 * is stated explicitly rather than inherited from an absence.
 */
class PartnerLinksRelationManager extends LinksRelationManager
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isPartner()) {
            return false;
        }

        return in_array(
            $ownerRecord->getKey(),
            $user->visibleReferralSourceIds() ?? [],
            true,
        );
    }

    /**
     * A partner may add rows to any organisation in their downline. The record
     * itself is bounded by `canViewForRecord()` above, which is what stops this
     * being a licence to write anywhere.
     */
    public static function canCreateRecords(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isPartner();
    }
}
