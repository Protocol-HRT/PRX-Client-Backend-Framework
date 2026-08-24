<?php

namespace App\Filament\Resources\Commerce\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Line items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('prescribe_rx_product_number')
                    ->label('PRX product #')
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('quantity'),
                TextColumn::make('unit_price')->money('usd')->placeholder('—'),
                TextColumn::make('line_total')->money('usd')->placeholder('—'),
                TextColumn::make('billing_period')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('id', 'asc');
    }
}
