<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ResolvesReferralScope;
use App\Models\Referral\ReferralSource;
use App\Services\Referral\ReferralMetrics;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who is producing — one row per organisation, every figure rolled up through
 * that organisation's own downline.
 *
 * WHAT A VIEWER SEES:
 *   - a group  → its DIRECT sub-organisations, each totalling its whole branch,
 *                which is what "aggregate broken down by my sub-orgs" means;
 *   - a leaf   → itself;
 *   - staff    → every top-level organisation, each totalling its branch.
 *
 * Direct children rather than every descendant on purpose: a row per leaf would
 * be the same money at a useless altitude, and each sub-org drills into its own
 * dashboard for that. (prescribe-rx's equivalent groups by whichever node owns
 * the order, so a grandchild appears beside its parent and the parent's row
 * excludes its own downline — confusing to read and easy to double-count by eye.)
 */
class ReferralBreakdownWidget extends TableWidget
{
    use HasWidgetShield, ResolvesReferralScope;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return $this->viewingAsPartner() ? 'Your organisations' : 'Referral sources';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->rowsQuery())
            ->defaultSort('name')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (ReferralSource $r): string => str_replace('_', ' ', ucfirst($r->type))),

                TextColumn::make('clicks')
                    ->label('Clicks')
                    ->alignRight()
                    ->state(fn (ReferralSource $r) => $this->metric($r, 'clicks'))
                    ->description(fn (ReferralSource $r): string => $this->metric($r, 'unique_visitors').' unique'),

                TextColumn::make('leads')
                    ->label('Leads')
                    ->alignRight()
                    ->state(fn (ReferralSource $r) => $this->metric($r, 'leads')),

                TextColumn::make('conversions')
                    ->label('Sales')
                    ->alignRight()
                    ->state(fn (ReferralSource $r) => $this->metric($r, 'conversions')),

                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->alignRight()
                    ->money('USD')
                    ->state(fn (ReferralSource $r) => $this->metric($r, 'revenue')),

                TextColumn::make('commission_owed')
                    ->label('Owed')
                    ->alignRight()
                    ->money('USD')
                    ->color('warning')
                    ->state(fn (ReferralSource $r) => $this->metric($r, 'commission_owed')),
            ])
            ->emptyStateHeading('Nothing referred yet')
            ->emptyStateDescription('Figures appear here as soon as a tracked link is used.');
    }

    /**
     * Memoised per render. Each column's state closure runs per row, so without
     * this a six-column table over twenty rows would compute the same funnel a
     * hundred and twenty times.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $funnels = [];

    private function metric(ReferralSource $source, string $key): int|float
    {
        $this->funnels[$source->id] ??= app(ReferralMetrics::class)->funnel($source);

        return $this->funnels[$source->id][$key];
    }

    private function rowsQuery(): Builder
    {
        $viewer = $this->referralViewer();

        if ($viewer) {
            $children = $viewer->children()->getQuery();

            // A leaf has no sub-organisations, so it is its own single row rather
            // than an empty table that reads as "you have no activity".
            return $viewer->children()->exists()
                ? $children
                : ReferralSource::query()->whereKey($viewer->id);
        }

        $ids = $this->referralScopeIds();

        // Staff (null scope) see the top level, each totalling its branch. An
        // empty scope is a partner with no organisation and must show nothing.
        return ReferralSource::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->whereNull('parent_id');
    }
}
