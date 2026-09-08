<?php

namespace App\Filament\Resources\Referrals\Tables;

use App\Models\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralSource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReferralSourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (ReferralSource $r): ?string => $r->company_name),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'sales_group' => 'info',
                        'partner' => 'warning',
                        'internal' => 'gray',
                        default => 'success',
                    }),

                TextColumn::make('parent.name')
                    ->label('Reports into')
                    ->placeholder('Top level')
                    ->toggleable(),

                TextColumn::make('children_count')
                    ->label('Sub-accounts')
                    ->counts('children')
                    ->alignRight()
                    ->toggleable(),

                TextColumn::make('links_count')
                    ->label('Codes')
                    ->counts('links')
                    ->alignRight(),

                // DERIVED, NOT STORED — this is the payoff of keeping a click
                // ledger instead of counter columns. These numbers are always
                // consistent with the rows a commission would be argued from,
                // because they ARE those rows.
                TextColumn::make('clicks')
                    ->label('Clicks')
                    ->alignRight()
                    ->state(fn (ReferralSource $r): int => ReferralClick::where('referral_source_id', $r->id)->count())
                    ->description(fn (ReferralSource $r): string => ReferralClick::where('referral_source_id', $r->id)
                        ->distinct('visitor_id')->count('visitor_id').' unique'),

                TextColumn::make('leads')
                    ->label('Leads')
                    ->alignRight()
                    ->state(fn (ReferralSource $r): int => Lead::where('referral_source_id', $r->id)->count()),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'affiliate' => 'Affiliate',
                    'sales_group' => 'Sales group',
                    'partner' => 'Partner',
                    'internal' => 'Internal',
                ]),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()])
            // No bulk delete. Removing referral sources en masse is never a
            // routine action, and the database refuses it anyway once codes
            // exist — an operator meeting that error mid-bulk-action learns
            // nothing useful.
            ->toolbarActions([]);
    }
}
