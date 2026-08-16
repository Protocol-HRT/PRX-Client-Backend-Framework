<?php

namespace App\Filament\Resources\Catalog\Packages\Schemas;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\Tag;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // ── Main column (2/3) ─────────────────────────────────
                Group::make([
                    Section::make('Package')
                        ->columnSpanFull()
                        ->columns(2)
                        ->components([
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
                                ->hintIcon(Heroicon::InformationCircle, 'Short sub-headline shown below the package name on listing cards.')
                                ->columnSpanFull(),
                            Textarea::make('short_description')
                                ->maxLength(2000)
                                ->rows(3)
                                ->hintIcon(Heroicon::InformationCircle, 'Brief description used in listing cards and API summary responses.')
                                ->columnSpanFull(),
                            Textarea::make('description')
                                ->rows(8)
                                ->hintIcon(Heroicon::InformationCircle, 'Full rich-text package description shown on the detail page.')
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
                                ->directory('catalog/packages')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->hintIcon(Heroicon::InformationCircle, 'Primary product image shown on listing cards and the package detail page.'),
                            FileUpload::make('banner_image_path')
                                ->label('Banner image')
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('catalog/packages/banners')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->hintIcon(Heroicon::InformationCircle, 'Wide banner image for the package hero section. Recommended: 1920×600px.')
                                ->helperText('Wide banner image for the package hero section.'),
                            FileUpload::make('gallery')
                                ->label('Gallery images')
                                ->image()
                                ->imageEditor()
                                ->multiple()
                                ->reorderable()
                                ->disk('public')
                                ->directory('catalog/packages/gallery')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->maxFiles(12)
                                ->hintIcon(Heroicon::InformationCircle, 'Additional images for an in-page gallery. Max 12 images, 5 MB each.'),
                        ]),
                    Section::make('Products in this package')
                        ->columnSpanFull()
                        ->components([
                            Select::make('products')
                                ->label('Products')
                                ->multiple()
                                ->relationship('products', 'name')
                                ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                                ->preload()
                                ->hintIcon(Heroicon::InformationCircle, 'Individual products bundled into this package. Used for display and provider mapping.')
                                ->searchable()
                                ->helperText('Products bundled into this package.'),
                        ]),
                    Section::make('UI / merchandising')
                        ->columnSpanFull()
                        ->columns(2)
                        ->components([
                            TextInput::make('badge_text')
                                ->label('Badge')
                                ->maxLength(32)
                                ->placeholder('e.g. Best Seller, New')
                                ->hintIcon(Heroicon::InformationCircle, 'Small promotional badge shown on listing cards, e.g. "Best Seller" or "New".')
                                ->helperText('Optional. Shown on listing cards.'),
                            TextInput::make('position')
                                ->numeric()
                                ->default(0)
                                ->hintIcon(Heroicon::InformationCircle, 'Controls display order. Lower numbers appear first.'),
                            Repeater::make('highlights')
                                ->label('Highlights')
                                ->hintIcon(Heroicon::InformationCircle, 'Bullet-point feature list displayed on the package detail page.')
                                ->helperText('One bullet per line. Displayed as a feature list.')
                                ->schema([
                                    TextInput::make('item')
                                        ->label('Highlight')
                                        ->required()
                                        ->hintIcon(Heroicon::InformationCircle, 'One feature or benefit bullet point.'),
                                ])
                                ->columnSpanFull()
                                ->reorderable()
                                ->addActionLabel('Add highlight'),
                        ]),
                    Section::make('Detail page content')
                        ->columnSpanFull()
                        ->description('Structured content blocks for the stack detail page. The frontend decides how each placement renders (sidebar accordions vs. description tabs).')
                        ->components([
                            Repeater::make('detail_sections')
                                ->hiddenLabel()
                                ->schema([
                                    TextInput::make('title')
                                        ->required()
                                        ->maxLength(255)
                                        ->hintIcon(Heroicon::InformationCircle, 'Heading shown on the accordion or tab, e.g. "How To Use".'),
                                    Select::make('placement')
                                        ->options([
                                            'accordion' => 'Sidebar accordion',
                                            'tab' => 'Description tab',
                                        ])
                                        ->default('accordion')
                                        ->required()
                                        ->native(false)
                                        ->hintIcon(Heroicon::InformationCircle, 'Where the frontend places this block on the detail page.'),
                                    Textarea::make('content')
                                        ->required()
                                        ->rows(5)
                                        ->columnSpanFull()
                                        ->hintIcon(Heroicon::InformationCircle, 'Block copy. Plain text / simple HTML.'),
                                ])
                                ->columns(2)
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                ->defaultItems(0)
                                ->addActionLabel('Add content block'),
                        ]),
                    Section::make('Provider mapping')
                        ->columnSpanFull()
                        ->description('Map this package to the matching record in the configured provider (e.g. PrescribeRx). Leave blank if not yet mapped.')
                        ->columns(2)
                        ->components([
                            TextInput::make('provider_package_id')
                                ->label('Provider package ID')
                                ->maxLength(36)
                                ->hintIcon(Heroicon::InformationCircle, 'UUID of the matching package record in the provider. Found in the provider admin.'),
                            TextInput::make('provider_package_sku')
                                ->label('Provider SKU')
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, "Provider's human-readable SKU for this package. Used in order submissions."),
                        ]),
                    Section::make('SEO overrides')
                        ->columnSpanFull()
                        ->description('Leave blank to fall back to the global SEO settings.')
                        ->columns(2)
                        ->components([
                            TextInput::make('meta_title')
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, 'SEO title for this package page. Defaults to package name if blank.'),
                            FileUpload::make('og_image_path')
                                ->label('OG image')
                                ->image()
                                ->disk('public')
                                ->directory('catalog/packages/og')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->hintIcon(Heroicon::InformationCircle, 'Open Graph image for social shares. 1200×630px recommended.'),
                            Textarea::make('meta_description')
                                ->maxLength(500)
                                ->rows(3)
                                ->hintIcon(Heroicon::InformationCircle, 'SEO meta description. 150–160 chars recommended.')
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
                                ->hintIcon(Heroicon::InformationCircle, 'Draft and Pending packages are not visible on the public site.')
                                ->native(false),
                        ]),
                    Section::make('Pricing')
                        ->columnSpanFull()
                        ->description('Display pricing. Used as transaction price when local checkout is configured; otherwise PRX is the source of truth.')
                        ->components([
                            TextInput::make('retail_price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->hintIcon(Heroicon::InformationCircle, 'Full retail price in dollars. Shown as the crossed-out price when a sale price is set.'),
                            TextInput::make('sale_price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->hintIcon(Heroicon::InformationCircle, 'Active sale price. If set, this is the price displayed to customers.'),
                            TextInput::make('price_suffix')
                                ->maxLength(32)
                                ->placeholder('e.g. /mo, starting at')
                                ->hintIcon(Heroicon::InformationCircle, 'Optional copy appended after the price, e.g. "/mo" or "starting at".'),
                            TextInput::make('cost')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->minValue(0)
                                ->hintIcon(Heroicon::InformationCircle, 'Internal cost — what the company pays. Used for reporting and P&L only; never shown on the storefront or public API.'),
                        ]),
                    Section::make('Categories & tags')
                        ->columnSpanFull()
                        ->components([
                            Select::make('category_ids')
                                ->label('Categories')
                                ->multiple()
                                ->relationship('categories', 'name')
                                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                                ->preload()
                                ->hintIcon(Heroicon::InformationCircle, 'Assigns this package to catalog categories.')
                                ->searchable(),
                            Select::make('tag_ids')
                                ->label('Tags')
                                ->multiple()
                                ->relationship('tags', 'name')
                                ->options(fn () => Tag::query()->orderBy('name')->pluck('name', 'id'))
                                ->preload()
                                ->hintIcon(Heroicon::InformationCircle, 'Optional tags for filtering and related-item suggestions.')
                                ->searchable(),
                        ]),
                    Section::make('Flags')
                        ->columnSpanFull()
                        ->columns(1)
                        ->components([
                            Toggle::make('is_featured')->label('Featured'),
                            Toggle::make('is_in_stock')->label('In stock')->default(true)
                                ->hintIcon(Heroicon::InformationCircle, 'Uncheck to hide this package from in-stock filters and surface an out-of-stock indicator on the frontend.'),
                            Toggle::make('requires_lab')->label('Requires lab work'),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}
