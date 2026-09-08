<?php

namespace App\Filament\Widgets;

use App\Enums\Payments\LeadPaymentStatus;
use App\Filament\Concerns\ResolvesReferralScope;
use App\Models\Lead;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Revenue and conversions over the last 30 days, scope-shaped.
 *
 * Two series on one chart because the interesting question is whether revenue is
 * moving with volume or with basket size — a month where sales fall and revenue
 * holds is a different business problem from one where both fall, and separate
 * charts hide it.
 */
class RevenueChartWidget extends ChartWidget
{
    use HasWidgetShield, ResolvesReferralScope;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Revenue and sales, last 30 days';

    protected int|string|array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return $this->viewingAsPartner()
            ? 'Your organisation and everything beneath it.'
            : 'Captured payments across all referred and direct sales.';
    }

    protected function getData(): array
    {
        $ids = $this->referralScopeIds();
        $start = Carbon::now()->subDays(29)->startOfDay();

        $rows = Lead::query()
            ->where('payment_status', LeadPaymentStatus::Captured->value)
            ->where('payment_processed_at', '>=', $start)
            // A partner sees only their tree; staff see everything, referred or
            // not, because the admin dashboard is the whole business.
            ->when($ids !== null, fn ($q) => $q->whereIn('referral_source_id', $ids))
            ->selectRaw('DATE(payment_processed_at) as day, COUNT(*) as sales, SUM(payment_amount) as revenue')
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $counts = Lead::query()
            ->where('payment_status', LeadPaymentStatus::Captured->value)
            ->where('payment_processed_at', '>=', $start)
            ->when($ids !== null, fn ($q) => $q->whereIn('referral_source_id', $ids))
            ->selectRaw('DATE(payment_processed_at) as day, COUNT(*) as sales')
            ->groupBy('day')
            ->pluck('sales', 'day');

        $labels = [];
        $revenue = [];
        $sales = [];

        // Every day in the window, including the empty ones — a chart that skips
        // quiet days compresses time and makes a gap look like a plateau.
        for ($day = $start->copy(); $day->lte(Carbon::now()); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('j M');
            $revenue[] = round((float) ($rows[$key] ?? 0), 2);
            $sales[] = (int) ($counts[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $revenue,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Sales',
                    'data' => $sales,
                    'borderColor' => 'rgb(99, 102, 241)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0)',
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                // Two axes, because dollars and counts share no scale — one axis
                // would flatten the smaller series into the baseline.
                'y' => ['position' => 'left', 'beginAtZero' => true],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
