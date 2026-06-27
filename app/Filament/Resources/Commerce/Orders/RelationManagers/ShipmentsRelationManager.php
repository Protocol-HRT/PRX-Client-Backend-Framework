<?php

namespace App\Filament\Resources\Commerce\Orders\RelationManagers;

use App\Enums\ShipmentStatus;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    protected static ?string $title = 'Shipments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tracking')
                ->columns(3)
                ->components([
                    Select::make('status')
                        ->options(ShipmentStatus::class)
                        ->required()
                        ->native(false),
                    TextInput::make('carrier')->maxLength(32),
                    TextInput::make('tracking_number')
                        ->maxLength(128)
                        ->copyable(),
                    TextInput::make('tracking_url')->url()->columnSpan(2),
                    TextInput::make('fulfillment_center')
                        ->label('Fulfillment center')
                        ->maxLength(64),
                    DateTimePicker::make('shipped_at'),
                    DateTimePicker::make('delivered_at'),
                    DateTimePicker::make('exception_at'),
                    Textarea::make('exception_reason')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ShipmentStatus $state): string => $state->color()),
                TextColumn::make('carrier')->placeholder('—'),
                TextColumn::make('tracking_number')
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('fulfillment_center')
                    ->label('FC')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('shipped_at')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('delivered_at')->dateTime()->placeholder('—')->sortable(),
            ])
            ->defaultSort('shipped_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
