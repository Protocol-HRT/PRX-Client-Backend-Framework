<?php

namespace App\Filament\Partner\Resources\Organizations\Tables;

use App\Models\Referral\ReferralSource;
use App\Services\Referral\ReferralMetrics;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        /** @var array<int, array<string, mixed>> $funnels */
        $funnels = [];

        $metric = function (ReferralSource $source, string $key) use (&$funnels) {
            $funnels[$source->id] ??= app(ReferralMetrics::class)->funnel($source);

            return $funnels[$source->id][$key];
        };

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (ReferralSource $r): string => str_replace('_', ' ', ucfirst($r->type))),

                TextColumn::make('parent.name')
                    ->label('Reports into')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('links_count')->label('Codes')->counts('links')->alignRight(),

                // Rolled up through each row's own downline.
                TextColumn::make('clicks')->label('Clicks')->alignRight()
                    ->state(fn (ReferralSource $r) => $metric($r, 'clicks')),

                TextColumn::make('leads')->label('Leads')->alignRight()
                    ->state(fn (ReferralSource $r) => $metric($r, 'leads')),

                TextColumn::make('conversions')->label('Sales')->alignRight()
                    ->state(fn (ReferralSource $r) => $metric($r, 'conversions')),

                TextColumn::make('commission_owed')->label('Owed')->alignRight()->money('USD')
                    ->color('warning')
                    ->state(fn (ReferralSource $r) => $metric($r, 'commission_owed')),

                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([]);
    }
}
