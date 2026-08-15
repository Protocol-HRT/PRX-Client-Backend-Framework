<?php

namespace App\Filament\Resources\Cms\RegionItems\Tables;

use App\Enums\Cms\Region;
use App\Enums\Cms\RegionItemKind;
use App\Models\Cms\RegionItem;
use App\Services\Cms\SectionRegistry;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegionItemsTable
{
    public static function configure(Table $table): Table
    {
        $registry = app(SectionRegistry::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['globalSection', 'menu']))
            ->reorderable('position')
            ->defaultSort('region')
            ->defaultGroup(
                Group::make('region')
                    ->getTitleFromRecordUsing(fn (RegionItem $record): string => $record->region->label())
            )
            ->columns([
                TextColumn::make('region')
                    ->badge()
                    ->formatStateUsing(fn (Region $state): string => $state->label()),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn (RegionItemKind $state): string => $state->label()),
                TextColumn::make('content')
                    ->label('Content')
                    ->state(fn (RegionItem $record): string => match ($record->kind) {
                        RegionItemKind::Section => $registry->labelFor((string) $record->section_type),
                        RegionItemKind::GlobalSection => $record->globalSection?->name ?? '⚠ missing',
                        RegionItemKind::Menu => $record->menu?->name ?? '⚠ missing',
                    }),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('position')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('region')
                    ->options(collect(Region::cases())
                        ->mapWithKeys(fn (Region $region): array => [$region->value => $region->label()])
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
