<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LeadsChartWidget;
use App\Filament\Widgets\OverviewStatsWidget;
use App\Filament\Widgets\RecentLeadsWidget;
use App\Filament\Widgets\ReferralBreakdownWidget;
use App\Filament\Widgets\ReferralFunnelWidget;
use App\Filament\Widgets\RevenueChartWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * The staff dashboard.
 *
 * THERE WAS NO DASHBOARD PAGE AT ALL until this landed — the panel was configured
 * `->pages([])` with no dashboard route, so `/admin` redirected away and the three
 * widgets that already existed (`OverviewStatsWidget`, `RecentLeadsWidget`,
 * `LeadsChartWidget`) were discovered by Filament and then rendered nowhere. They
 * are wired up here rather than rewritten.
 *
 * Order is deliberate, reading top to bottom as "how is the business doing?" →
 * "where is it coming from?" → "who needs attention?":
 *   1. Business overview — leads, conversion, encounters, revenue.
 *   2. Referral funnel — clicks through to commission owed.
 *   3. Referral breakdown — which organisations are producing.
 *   4. Revenue and sales trend.
 *   5. Lead volume trend.
 *   6. Recent leads — the actionable list.
 *
 * Every referral widget is scope-shaped (see ResolvesReferralScope), so the same
 * classes serve the partner portal with a partner's own numbers. That is the
 * reason to build them as widgets rather than as one bespoke page.
 */
class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -100;

    public function getWidgets(): array
    {
        return [
            OverviewStatsWidget::class,
            ReferralFunnelWidget::class,
            ReferralBreakdownWidget::class,
            RevenueChartWidget::class,
            LeadsChartWidget::class,
            RecentLeadsWidget::class,
        ];
    }

    public function getColumns(): array|int
    {
        return 2;
    }
}
