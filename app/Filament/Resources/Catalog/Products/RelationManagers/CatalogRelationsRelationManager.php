<?php

namespace App\Filament\Resources\Catalog\Products\RelationManagers;

use App\Enums\CatalogRelationType;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Manages typed links from this catalog item to other products/packages:
 * "Related" (similar items) and "Pairs With" (suggested companions for
 * custom stacks). Registered on both the Product and Package resources
 * via the polymorphic catalogRelations() relationship.
 */
class CatalogRelationsRelationManager extends RelationManager
{
    protected static string $relationship = 'catalogRelations';

    protected static ?string $title = 'Related & Pairs With';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('relation_type')
                    ->label('Relation')
                    ->options(CatalogRelationType::class)
                    ->default(CatalogRelationType::Related->value)
                    ->required()
                    ->native(false)
                    ->hintIcon(Heroicon::InformationCircle, '"Related" = similar items shown on the detail page. "Pairs With" = suggested companions for building a custom stack.'),
                MorphToSelect::make('related')
                    ->label('Catalog item')
                    ->types([
                        MorphToSelect\Type::make(Product::class)
                            ->titleAttribute('name'),
                        MorphToSelect\Type::make(Package::class)
                            ->titleAttribute('name'),
                    ])
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('relation_type')
                    ->label('Relation')
                    ->badge()
                    ->formatStateUsing(fn (CatalogRelationType $state): string => $state->label()),
                TextColumn::make('related_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextColumn::make('related.name')
                    ->label('Item')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add relation'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
