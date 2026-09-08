<?php

namespace App\Filament\Partner\Resources;

use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The base every partner-panel resource MUST extend.
 *
 * ROW SCOPING LIVES HERE AND NOWHERE ELSE. prescribe-rx's own tenancy audit found
 * twenty confirmed cross-tenant leaks, and its recorded root cause was that only
 * four of two hundred and seventy-seven models carried the scope trait — the
 * failure mode is never "the scope was wrong", it is "somebody added a screen and
 * did not think about it". So scoping is inherited rather than remembered, and
 * `PartnerPanelStructureTest` asserts that every resource registered on this
 * panel extends this class. Forgetting is a failing test, not a review question.
 *
 * Subclasses implement `scopeColumn()` — the column on their own model that
 * carries the referral source — and get the rest for free.
 *
 * Note this is NOT a Shield-managed resource. See PartnerPanelProvider for why
 * Shield is deliberately absent from this panel.
 */
abstract class PartnerResource extends Resource
{
    /** The column on this resource's model holding the referral source id. */
    abstract public static function scopeColumn(): string;

    /**
     * The ids this viewer may see: their organisation and its whole downline.
     *
     * `[]` when identity cannot be resolved — never "everything". A scope that
     * degrades open on a session edge case is the leak.
     *
     * @return list<int>
     */
    protected static function visibleSourceIds(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        // Staff get `null` from this method, meaning "unscoped" — but staff
        // cannot reach this panel at all (User::canAccessPanel), so null here
        // would be a contradiction. Treat it as empty rather than as a licence.
        return $user->visibleReferralSourceIds() ?? [];
    }

    /**
     * AUTHORIZATION HERE IS PANEL MEMBERSHIP + ROW SCOPING, NOT THE MODEL POLICY,
     * and that is a deliberate override of Filament's default.
     *
     * Shield keys its permissions on the MODEL, so `OrganizationResource` over
     * `ReferralSource` consults the admin panel's `ReferralSourcePolicy` — and
     * partner roles hold ZERO permissions by design, so every partner screen 403s.
     * The fix is not to grant partners `ViewAny:ReferralSource`: that same key
     * governs the STAFF resource, so granting it would hand partners the admin's
     * unscoped organisation list the moment anything let them reach it.
     *
     * So the boundary is stated here instead, and it is narrower than a
     * permission: you must be a partner, on the partner panel, with a resolvable
     * organisation — and every row you then see passes `getEloquentQuery()`.
     * `canView()`/`canEdit()` below re-check membership per record.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        // NOT `!== []`. A partner whose organisation was deleted resolves to an
        // empty set and gets an empty table, which is correct and readable; the
        // thing that must never happen is an unscoped one, and that is
        // impossible because visibleReferralSourceIds() returns null only for
        // staff, who cannot reach this panel at all.
        return $user instanceof User && $user->isPartner();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn(static::scopeColumn(), static::visibleSourceIds());
    }

    /**
     * Belt and braces on the single-record routes. `getEloquentQuery()` already
     * bounds the list and the record binding, but an action or a hand-built
     * `find()` in a subclass would bypass it, so membership is re-checked here.
     */
    public static function canView(Model $record): bool
    {
        return in_array($record->{static::scopeColumn()}, static::visibleSourceIds(), true);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        // Partners never delete. Codes, organisations and people all carry
        // history that a commission is argued from; deactivation is the verb.
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }
}
