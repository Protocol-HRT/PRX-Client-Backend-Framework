<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Widgets\ReferralBreakdownWidget;
use App\Filament\Widgets\ReferralFunnelWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * The partner dashboard — ONE page for every level of the tree.
 *
 * A national group, a regional group and a solo affiliate all load this, and the
 * widgets shape themselves to the viewer's scope: a group sees its whole downline
 * with a row per direct sub-organisation, a leaf sees itself. That is deliberate
 * and is the reason the widgets are shared with the staff dashboard rather than
 * duplicated — two implementations drift, and the one an affiliate sees is the
 * one nobody checks.
 *
 * The scoping lives in the widgets (ResolvesReferralScope), not here, so a new
 * widget added to this page cannot forget it by being written in the wrong place.
 */
class PartnerDashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Dashboard';

    public function getWidgets(): array
    {
        return [
            ReferralFunnelWidget::class,
            ReferralBreakdownWidget::class,
            RevenueChartWidget::class,
        ];
    }

    public function getColumns(): array|int
    {
        return 2;
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();
        $source = $user instanceof User ? $user->referralSource : null;

        if (! $source) {
            return null;
        }

        // Naming the scope on the page removes the commonest support question a
        // hierarchy produces — "are these my numbers or my whole team's?"
        return $source->children()->exists()
            ? "{$source->name} — including all sub-organisations"
            : $source->name;
    }
}
