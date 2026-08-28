<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\LeadDisposition;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentLeadsWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Recent Leads';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Lead::query()->latest()->limit(15))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Captured')
                    ->since()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => LeadDisposition::labelFor($state))
                    ->color(fn (?string $state): string => LeadDisposition::colorFor($state)),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn (Lead $record): string => trim("{$record->first_name} {$record->last_name}"))
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('cart_subtotal')
                    ->label('Subtotal')
                    ->money('usd')
                    ->placeholder('—'),
                TextColumn::make('checkout_path')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('utm_source')
                    ->label('Source')
                    ->placeholder('—'),
            ]);
    }
}
