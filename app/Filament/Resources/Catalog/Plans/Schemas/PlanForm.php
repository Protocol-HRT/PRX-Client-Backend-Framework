<?php

namespace App\Filament\Resources\Catalog\Plans\Schemas;

use App\Enums\BillingMode;
use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use App\Enums\RebillStrategy;
use App\Models\Catalog\Package;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // ── Main column (2/3) ─────────────────────────────────
                Group::make([
                    Section::make('Plan')
                        ->columnSpanFull()
                        ->columns(2)
                        ->components([
                            Select::make('package_id')
                                ->label('Package')
                                ->options(fn () => Package::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->hintIcon(Heroicon::InformationCircle, 'Package this plan is a subscription cadence for. Leave blank for a standalone subscription.'),
                            Select::make('billing_period')
                                ->options(BillingPeriod::class)
                                ->default(BillingPeriod::Monthly->value)
                                ->required()
                                ->hintIcon(Heroicon::InformationCircle, 'Billing frequency sent to the provider at intake.')
                                ->native(false),
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->hintIcon(Heroicon::InformationCircle, 'Display name shown on the storefront and in admin listings.')
                                ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                    if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                        $set('slug', Str::slug($state));
                                    }
                                }),
                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->alphaDash()
                                ->hintIcon(Heroicon::InformationCircle, 'URL-friendly identifier. Auto-generated; change with caution — breaks existing links.'),
                            TextInput::make('subtitle')
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, 'Short sub-headline shown in the plan-selection UI.')
                                ->columnSpanFull(),
                            Textarea::make('short_description')
                                ->maxLength(2000)
                                ->rows(3)
                                ->hintIcon(Heroicon::InformationCircle, 'Brief description used in plan-selection cards.')
                                ->columnSpanFull(),
                            Textarea::make('description')
                                ->rows(8)
                                ->hintIcon(Heroicon::InformationCircle, 'Full rich-text plan description shown on the detail page.')
                                ->columnSpanFull(),
                        ]),
                    Section::make('Imagery')
                        ->columnSpanFull()
                        ->components([
                            FileUpload::make('hero_image_path')
                                ->label('Hero image')
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('catalog/plans')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->hintIcon(Heroicon::InformationCircle, 'Primary image shown on plan-selection cards.'),
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
                                ->hintIcon(Heroicon::InformationCircle, 'Additional images for an in-page gallery. Max 12 images, 5 MB each.'),
                        ]),
                    Section::make('Provider mapping')
                        ->columnSpanFull()
                        ->description('Map this plan to the matching record in the configured provider (e.g. PrescribeRx). Leave blank if not yet mapped.')
                        ->columns(2)
                        ->components([
                            TextInput::make('provider_plan_id')
                                ->label('Provider plan ID')
                                ->maxLength(36)
                                ->hintIcon(Heroicon::InformationCircle, 'UUID of the matching plan in the provider. Found in the provider admin.'),
                            TextInput::make('provider_plan_sku')
                                ->label('Provider SKU')
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, "Provider's human-readable SKU for this plan. Used in order submissions."),
                            Textarea::make('provider_product_ids')
                                ->label('Provider product IDs (JSON)')
                                ->hintIcon(Heroicon::InformationCircle, 'JSON array of provider product UUIDs to pass at intake, e.g. ["uuid-1","uuid-2"].')
                                ->helperText('JSON array of provider product IDs for intake, e.g. ["uuid-1","uuid-2"]')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    Section::make('SEO overrides')
                        ->columnSpanFull()
                        ->columns(2)
                        ->components([
                            TextInput::make('meta_title')
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, 'SEO title for this plan page. Defaults to plan name if blank.'),
                            FileUpload::make('og_image_path')
                                ->label('OG image')
                                ->image()
                                ->disk('public')
                                ->directory('catalog/plans/og')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->hintIcon(Heroicon::InformationCircle, 'Open Graph image for social shares. 1200×630px recommended.'),
                            Textarea::make('meta_description')
                                ->maxLength(500)
                                ->rows(3)
                                ->hintIcon(Heroicon::InformationCircle, 'SEO meta description for this plan. 150–160 chars recommended.')
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(2),

                // ── Sidebar (1/3) ─────────────────────────────────────
                Group::make([
                    Section::make('Status')
                        ->columnSpanFull()
                        ->components([
                            Select::make('status')
                                ->options(CatalogStatus::class)
                                ->default(CatalogStatus::Draft->value)
                                ->required()
                                ->hintIcon(Heroicon::InformationCircle, 'Draft and Pending plans are not visible on the public site.')
                                ->native(false),
                        ]),
                    Section::make('Pricing')
                        ->columnSpanFull()
                        ->description('Display pricing. Used as transaction price when local checkout is configured.')
                        ->components([
                            TextInput::make('retail_price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->hintIcon(Heroicon::InformationCircle, 'Full retail price. Shown as the crossed-out price when a sale price is set.'),
                            TextInput::make('sale_price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->hintIcon(Heroicon::InformationCircle, 'Active sale price. If set, this is the price displayed to customers.'),
                            TextInput::make('intro_price')
                                ->label('Intro price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->hintIcon(Heroicon::InformationCircle, 'Introductory first-billing-cycle price (e.g. "First month $179.99"). Later cycles bill at the retail/sale price.'),
                            TextInput::make('price_suffix')
                                ->maxLength(32)
                                ->placeholder('e.g. /mo, every 3 mo')
                                ->hintIcon(Heroicon::InformationCircle, 'Optional copy appended after the price.'),
                            TextInput::make('cost')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->minValue(0)
                                ->hintIcon(Heroicon::InformationCircle, 'Internal cost per billing cycle — what the company pays. Used for reporting and P&L only; never exposed publicly.'),
                        ]),
                    Section::make('Subscription')
                        ->columnSpanFull()
                        ->components([
                            Select::make('billing_mode')
                                ->options(BillingMode::class)
                                ->native(false)
                                ->label('Billing mode')
                                ->placeholder('Not set')
                                ->hintIcon(Heroicon::InformationCircle, 'Commercial billing structure, mirroring the provider: prepaid term, recurring, installment, or externally billed.'),
                            TextInput::make('term_months')
                                ->label('Term (months)')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(36)
                                ->hintIcon(Heroicon::InformationCircle, 'Explicit month count sent to provider. 1=monthly, 3=quarterly, 6=semi-annual, 12=annual.'),
                            Toggle::make('is_recurring')
                                ->label('Recurring / subscription')
                                ->helperText('Off = one-time purchase.')
                                ->live(),
                            Toggle::make('is_default')
                                ->label('Pre-selected plan')
                                ->helperText('Pre-selected when the package page loads.'),
                            Select::make('rebill_strategy')
                                ->options(RebillStrategy::class)
                                ->native(false)
                                ->label('Rebill strategy')
                                ->visible(fn ($get) => $get('is_recurring'))
                                ->hintIcon(Heroicon::InformationCircle, 'How the subscription rebills after the initial order.'),
                            TextInput::make('trial_days')
                                ->label('Trial days')
                                ->numeric()
                                ->minValue(0)
                                ->visible(fn ($get) => $get('is_recurring'))
                                ->hintIcon(Heroicon::InformationCircle, 'Free trial days before the first rebill. 0 = no trial.'),
                        ]),
                    Section::make('Flags')
                        ->columnSpanFull()
                        ->components([
                            Toggle::make('is_featured')->label('Featured'),
                            Toggle::make('requires_lab')->label('Requires lab work'),
                        ]),
                    Section::make('Merchandising')
                        ->columnSpanFull()
                        ->components([
                            TextInput::make('badge_text')
                                ->label('Badge')
                                ->maxLength(32)
                                ->placeholder('e.g. Best Value, Most Popular')
                                ->hintIcon(Heroicon::InformationCircle, 'Small promotional badge shown in plan-selection UI.'),
                            TextInput::make('position')
                                ->numeric()
                                ->default(0)
                                ->hintIcon(Heroicon::InformationCircle, 'Controls display order in plan-selection UI. Lower = first.'),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}
