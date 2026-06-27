<?php

namespace App\Filament\Resources\Catalog\Plans\Schemas;

use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use App\Enums\RebillStrategy;
use App\Models\Catalog\Package;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan')
                    ->columns(2)
                    ->components([
                        Select::make('package_id')
                            ->label('Package')
                            ->options(fn () => Package::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Plan is a reship/subscription cadence wrapping this package. Optional — leave blank for a standalone subscription.'),
                        Select::make('billing_period')
                            ->options(BillingPeriod::class)
                            ->default(BillingPeriod::Monthly->value)
                            ->required()
                            ->native(false),
                        TextInput::make('term_months')
                            ->label('Term (months)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(36)
                            ->helperText('Explicit month count sent to provider at intake. 1=monthly, 3=quarterly, 6=semi-annual, 9=9-month, 12=annual.'),
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
                        TextInput::make('subtitle')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('short_description')
                            ->maxLength(2000)
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(8)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(CatalogStatus::class)
                            ->default(CatalogStatus::Draft->value)
                            ->required()
                            ->native(false),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0),
                    ]),
                Section::make('Pricing')
                    ->description('Display pricing. Used as the actual transaction price when local checkout is the configured path; otherwise PRX is the source of truth.')
                    ->columns(3)
                    ->components([
                        TextInput::make('retail_price')->numeric()->prefix('$')->step(0.01),
                        TextInput::make('sale_price')->numeric()->prefix('$')->step(0.01),
                        TextInput::make('price_suffix')
                            ->maxLength(32)
                            ->placeholder('e.g. /mo, every 3 mo'),
                    ]),
                Section::make('Imagery')
                    ->columns(2)
                    ->components([
                        FileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('catalog/plans')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                        FileUpload::make('gallery')
                            ->label('Gallery images')
                            ->image()
                            ->imageEditor()
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('catalog/plans/gallery')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->maxFiles(12)
                            ->columnSpanFull(),
                    ]),
                Section::make('Flags')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_featured')->label('Featured'),
                        Toggle::make('requires_lab')->label('Requires lab work'),
                        Toggle::make('is_recurring')
                            ->label('Recurring / subscription')
                            ->helperText('Enables rebill cycle. Off = one-time purchase.')
                            ->live(),
                        Toggle::make('is_default')
                            ->label('Pre-selected plan')
                            ->helperText('This plan is pre-selected when the package page loads.'),
                    ]),
                Section::make('Subscription config')
                    ->columns(2)
                    ->visible(fn ($get) => $get('is_recurring'))
                    ->components([
                        Select::make('rebill_strategy')
                            ->options(RebillStrategy::class)
                            ->native(false)
                            ->label('Rebill strategy'),
                        TextInput::make('trial_days')
                            ->label('Trial days')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Free trial before first rebill. 0 = no trial.'),
                    ]),
                Section::make('UI / merchandising')
                    ->components([
                        TextInput::make('badge_text')
                            ->label('Badge')
                            ->maxLength(32)
                            ->placeholder('e.g. Best Value, Most Popular')
                            ->helperText('Small badge shown in plan selection UI. Optional.'),
                    ]),
                Section::make('Provider mapping')
                    ->description('Map this plan to the matching record in the configured provider (e.g. PrescribeRx). Leave blank if not yet mapped.')
                    ->columns(2)
                    ->components([
                        TextInput::make('provider_plan_id')
                            ->label('Provider plan ID')
                            ->maxLength(36),
                        TextInput::make('provider_plan_sku')
                            ->label('Provider SKU')
                            ->maxLength(255),
                        Textarea::make('provider_product_ids')
                            ->label('Provider product IDs (JSON)')
                            ->helperText('JSON array of provider product IDs to pass at intake for this plan, e.g. ["uuid-1","uuid-2"]')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('SEO overrides')
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_title')->maxLength(255),
                        FileUpload::make('og_image_path')
                            ->label('OG image')
                            ->image()
                            ->disk('public')
                            ->directory('catalog/plans/og')
                            ->visibility('public')
                            ->maxSize(5120),
                        Textarea::make('meta_description')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
