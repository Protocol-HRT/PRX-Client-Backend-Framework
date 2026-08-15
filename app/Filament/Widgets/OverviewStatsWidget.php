<?php

namespace App\Filament\Widgets;

use App\Enums\EncounterStatus;
use App\Enums\LeadStatus;
use App\Enums\OrderStatus;
use App\Models\Commerce\Encounter;
use App\Models\Commerce\Order;
use App\Models\Lead;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class OverviewStatsWidget extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $leadsThisMonth = Lead::where('created_at', '>=', $startOfMonth)->count();
        $leadsLastMonth = Lead::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        $leadDelta = $leadsThisMonth - $leadsLastMonth;
        $leadDeltaLabel = ($leadDelta >= 0 ? '+' : '').number_format($leadDelta).' vs last month';

        $leadSparkline = collect(range(6, 0))
            ->map(fn (int $daysAgo) => Lead::whereDate('created_at', now()->subDays($daysAgo))->count())
            ->toArray();

        $conversionsThisMonth = Lead::where('created_at', '>=', $startOfMonth)
            ->whereIn('status', [LeadStatus::HandedOff->value, LeadStatus::Completed->value])
            ->count();

        $conversionRate = $leadsThisMonth > 0
            ? number_format(($conversionsThisMonth / $leadsThisMonth) * 100, 1).'% conversion'
            : 'No leads yet';

        $activeEncounters = Encounter::whereIn('status', [
            EncounterStatus::Submitted->value,
            EncounterStatus::InReview->value,
        ])->count();

        $revenueThisMonth = (float) Order::where('placed_at', '>=', $startOfMonth)
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Refunded->value])
            ->sum('total_amount');

        $revenueLastMonth = (float) Order::whereBetween('placed_at', [$lastMonthStart, $lastMonthEnd])
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Refunded->value])
            ->sum('total_amount');

        $revenueDelta = $revenueThisMonth - $revenueLastMonth;
        $revenueDeltaLabel = ($revenueDelta >= 0 ? '+' : '-').'$'.number_format(abs($revenueDelta), 2).' vs last month';

        return [
            Stat::make('Leads this month', number_format($leadsThisMonth))
                ->description($leadDeltaLabel)
                ->descriptionIcon($leadDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($leadSparkline)
                ->color('primary'),

            Stat::make('Conversions this month', number_format($conversionsThisMonth))
                ->description($conversionRate)
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Active encounters', number_format($activeEncounters))
                ->description('Submitted + in review')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('warning'),

            Stat::make('Revenue this month', '$'.number_format($revenueThisMonth, 2))
                ->description($revenueDeltaLabel)
                ->descriptionIcon($revenueDelta >= 0 ? 'heroicon-m-banknotes' : 'heroicon-m-arrow-trending-down')
                ->color($revenueDelta >= 0 ? 'success' : 'danger'),
        ];
    }
}
