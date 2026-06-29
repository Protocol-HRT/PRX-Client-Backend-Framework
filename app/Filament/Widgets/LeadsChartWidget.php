<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class LeadsChartWidget extends ChartWidget
{
    protected ?string $heading = 'New Leads — last 30 days';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $start = Carbon::now()->subDays(29)->startOfDay();

        $counts = Lead::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $start)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $dates = collect(range(29, 0))->map(fn (int $daysAgo) => Carbon::now()->subDays($daysAgo));

        return [
            'datasets' => [
                [
                    'label' => 'New Leads',
                    'data' => $dates->map(fn (Carbon $date) => (int) $counts->get($date->format('Y-m-d'), 0))->toArray(),
                    'borderColor' => 'rgb(13, 148, 136)',
                    'backgroundColor' => 'rgba(13, 148, 136, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => $dates->map(fn (Carbon $date) => $date->format('M j'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
