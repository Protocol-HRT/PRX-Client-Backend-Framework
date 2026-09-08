<?php

namespace App\Filament\Partner\Resources\Organizations\RelationManagers;

use App\Filament\Resources\Referrals\RelationManagers\RepsRelationManager;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The People panel, on the partner portal.
 *
 * IT EXISTS BECAUSE THE SHARED ONE IS INVISIBLE HERE. Filament authorises a
 * relation manager through the RELATED model's policy
 * (`RelationManager::canViewForRecord` → `viewAny`), so the staff panel's
 * `UserPolicy::viewAny` gates it on `ViewAny:User` — a permission partner roles
 * hold by design, because they hold none. The panel simply did not render, and a
 * partner could not add anyone. (`LinksRelationManager` survives only because
 * `ReferralLink` happens to have no policy — an accident, not a design, which is
 * why its partner subclass below is explicit too.)
 *
 * Granting partners `ViewAny:User` would be the wrong fix, for the same reason it
 * was wrong for the resources: that key also governs the STAFF `UserResource` and
 * its list of every account in the business.
 *
 * So authorisation is restated here as membership of the viewer's downline — the
 * same rule PartnerResource applies to rows.
 */
class PartnerRepsRelationManager extends RepsRelationManager
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
