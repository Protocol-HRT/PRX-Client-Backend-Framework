<?php

namespace App\Filament\Resources\Catalog\Packages\RelationManagers;

use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PlansRelationManager extends RelationManager
{
    protected static string $relationship = 'plans';

    protected static ?string $title = 'Plans';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash(),
                        Select::make('billing_period')
                            ->options(BillingPeriod::class)
                            ->default(BillingPeriod::Monthly->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->options(CatalogStatus::class)
                            ->default(CatalogStatus::Draft->value)
                            ->required()
                            ->native(false),
                        TextInput::make('subtitle')->maxLength(255)->columnSpanFull(),
                        Textarea::make('short_description')
                            ->maxLength(2000)
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Pricing')
                    ->columns(3)
                    ->components([
                        TextInput::make('retail_price')->numeric()->prefix('$')->step(0.01),
                        TextInput::make('sale_price')->numeric()->prefix('$')->step(0.01),
                        TextInput::make('price_suffix')
                            ->maxLength(32)
                            ->placeholder('e.g. /mo, every 3 mo'),
                    ]),
                Section::make('Flags')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_featured')->label('Featured'),
                        Toggle::make('requires_lab')->label('Requires lab work'),
                        Toggle::make('is_recurring')
                            ->label('Recurring / subscription')
                            ->helperText('Enables rebill cycle. Off = one-time purchase.'),
                        Toggle::make('is_default')
                            ->label('Pre-selected plan')
                            ->helperText('This plan is pre-selected when the package page loads.'),
                    ]),
                Section::make('Provider mapping')
                    ->columns(2)
                    ->components([
                        TextInput::make('provider_plan_id')
                            ->label('Provider plan ID')
                            ->maxLength(36),
                        TextInput::make('provider_plan_sku')
                            ->label('Provider SKU')
                            ->maxLength(255),
                        TextInput::make('badge_text')
                            ->label('Badge')
                            ->maxLength(32)
                            ->placeholder('e.g. Best Value'),
                        TextInput::make('term_months')
                            ->label('Term (months)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(36),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('billing_period')
                    ->badge()
                    ->formatStateUsing(fn (BillingPeriod $state): string => $state->label()),
                TextColumn::make('sale_price')
                    ->money('usd')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CatalogStatus $state): string => $state->color()),
                IconColumn::make('is_featured')->boolean()->label('Featured'),
                TextColumn::make('updated_at')->since(),
            ])
            ->headerActions([
                CreateAction::make(),
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
