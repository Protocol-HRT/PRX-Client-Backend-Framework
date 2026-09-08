<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ResolvesReferralScope;
use App\Services\Referral\ReferralMetrics;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The referral funnel, shaped to whoever is looking.
 *
 * Staff see every referred visitor; a partner sees their own organisation and its
 * entire downline. Same widget, same numbers, different scope — see
 * ResolvesReferralScope.
 */
class ReferralFunnelWidget extends StatsOverviewWidget
{
    use HasWidgetShield, ResolvesReferralScope;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '120s';

    protected function getStats(): array
    {
        $m = app(ReferralMetrics::class)->funnelForIds($this->referralScopeIds());

        return [
            Stat::make('Referred clicks', number_format($m['clicks']))
                ->description($m['unique_visitors'].' unique visitors')
                ->descriptionIcon('heroicon-m-cursor-arrow-ripple')
                ->color('gray'),

            Stat::make('Referred leads', number_format($m['leads']))
                ->description($m['click_to_lead'].'% of visitors')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Conversions', number_format($m['conversions']))
                ->description($m['lead_to_conversion'].'% of leads')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($m['conversions'] > 0 ? 'success' : 'gray'),

            Stat::make('Referred revenue', '$'.number_format($m['revenue'], 2))
                // Captured only — an authorisation is not money, so this figure
                // never flatters itself with sales nobody was paid for.
                ->description('Captured payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Commission owed', '$'.number_format($m['commission_owed'], 2))
                ->description('Pending and approved, not yet paid')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color($m['commission_owed'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
