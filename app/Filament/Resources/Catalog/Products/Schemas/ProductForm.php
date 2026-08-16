<?php

namespace App\Filament\Resources\Catalog\Products\Schemas;

use App\Enums\CatalogStatus;
use App\Enums\InventoryStatus;
use App\Models\Catalog\Category;
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

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // ── Main column (2/3) ─────────────────────────────────
                Group::make([
                    Section::make('Product')
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
                                ->hintIcon(Heroicon::InformationCircle, 'URL-friendly identifier. Auto-generated; change with caution — breaks existing links.')
                                ->helperText('Lowercase letters, numbers, hyphens.'),
                            TextInput::make('subtitle')
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, 'Short sub-headline shown below the product name on listing cards.')
                                ->columnSpanFull(),
                            Textarea::make('short_description')
                                ->maxLength(2000)
                                ->rows(3)
                                ->hintIcon(Heroicon::InformationCircle, 'Brief description used in listing cards and API summary responses.')
                                ->columnSpanFull(),
                            Textarea::make('description')
                                ->rows(8)
                                ->columnSpanFull()
                                ->hintIcon(Heroicon::InformationCircle, 'Full product description shown on the detail page.')
                                ->helperText('Long-form description for the product detail page.'),
                        ]),
                    Section::make('Classification')
                        ->columnSpanFull()
                        ->description('Clinical taxonomy and physical form. Manage the option lists under Shop → Product Classes / Types / Forms / Administration Methods / Units.')
                        ->columns(2)
                        ->components([
                            Select::make('product_class_id')
                                ->label('Product class')
                                ->relationship('productClass', 'name', fn ($query) => $query->active()->orderBy('position'))
                                ->preload()
                                ->searchable()
                                ->hintIcon(Heroicon::InformationCircle, 'Top-level clinical grouping, e.g. Peptides, HRT, GLP-1.'),
                            Select::make('product_type_id')
                                ->label('Product type')
                                ->relationship('productType', 'name', fn ($query) => $query->active()->orderBy('position'))
                                ->preload()
                                ->searchable()
                                ->hintIcon(Heroicon::InformationCircle, 'Specific type within the class, e.g. a particular therapy line.'),
                            Select::make('product_form_id')
                                ->label('Product form')
                                ->relationship('productForm', 'name', fn ($query) => $query->active()->orderBy('position'))
                                ->preload()
                                ->searchable()
                                ->hintIcon(Heroicon::InformationCircle, 'Physical form: vial, troche, capsule, cream, nasal inhaler, etc.'),
                            Select::make('administration_method_id')
                                ->label('Administration method')
                                ->relationship('administrationMethod', 'name', fn ($query) => $query->active()->orderBy('position'))
                                ->preload()
                                ->searchable()
                                ->hintIcon(Heroicon::InformationCircle, 'Route of delivery: oral, sub-q injection, topical, etc.'),
                            TextInput::make('volume')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.0001)
                                ->hintIcon(Heroicon::InformationCircle, 'Container volume or total content amount, e.g. 10 for a 10 mg vial or 3 for a 3 ml vial.'),
                            Select::make('volume_unit_id')
                                ->label('Volume unit')
                                ->relationship('volumeUnit', 'abbreviation', fn ($query) => $query->active()->orderBy('position'))
                                ->preload()
                                ->hintIcon(Heroicon::InformationCircle, 'Unit for the volume value: mg, ml, g, etc.'),
                        ]),
                    Section::make('Imagery')
                        ->columnSpanFull()
                        ->components([
                            FileUpload::make('hero_image_path')
                                ->label('Hero image')
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('catalog/products')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->hintIcon(Heroicon::InformationCircle, 'Primary product image shown on listing cards and the product detail page.'),
                            FileUpload::make('gallery')
                                ->label('Gallery images')
                                ->image()
                                ->imageEditor()
                                ->multiple()
                                ->reorderable()
                                ->disk('public')
                                ->directory('catalog/products/gallery')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->maxFiles(12)
                                ->hintIcon(Heroicon::InformationCircle, 'Additional images for an in-page gallery. Max 12 images, 5 MB each.'),
                        ]),
                    Section::make('UI / merchandising')
                        ->columnSpanFull()
                        ->columns(2)
                        ->components([
                            TextInput::make('badge_text')
                                ->label('Badge')
                                ->maxLength(32)
                                ->placeholder('e.g. Best Seller, New')
                                ->hintIcon(Heroicon::InformationCircle, 'Small promotional badge shown on listing cards.')
                                ->helperText('Optional. Shown on listing cards.'),
                            TextInput::make('position')
                                ->numeric()
                                ->default(0)
                                ->hintIcon(Heroicon::InformationCircle, 'Controls display order. Lower numbers appear first.'),
                            Repeater::make('highlights')
                                ->label('Highlights')
                                ->hintIcon(Heroicon::InformationCircle, 'Bullet-point feature list displayed on the product detail page.')
                                ->helperText('One bullet per line.')
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
                    Section::make('Provider mapping')
                        ->columnSpanFull()
                        ->description('Map this product to the matching record in the configured provider (e.g. PrescribeRx). Leave blank if not yet mapped.')
                        ->columns(2)
                        ->components([
                            TextInput::make('provider_product_id')
                                ->label('Provider product ID')
                                ->maxLength(36)
                                ->hintIcon(Heroicon::InformationCircle, 'UUID of the matching product in the provider. Found in the provider admin.'),
                            TextInput::make('provider_product_sku')
                                ->label('Provider SKU')
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, "Provider's human-readable SKU. Used in order submissions."),
                        ]),
                    Section::make('SEO overrides')
                        ->columnSpanFull()
                        ->description('Leave blank to fall back to the global SEO settings.')
                        ->columns(2)
                        ->components([
                            TextInput::make('meta_title')
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, 'SEO title for this product page. Defaults to product name if blank.'),
                            FileUpload::make('og_image_path')
                                ->label('OG image')
                                ->image()
                                ->disk('public')
                                ->directory('catalog/products/og')
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
                                ->hintIcon(Heroicon::InformationCircle, 'Draft and Pending products are not visible on the public site.')
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
                                ->hintIcon(Heroicon::InformationCircle, 'Full retail price. Shown as the crossed-out price when a sale price is set.'),
                            TextInput::make('sale_price')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->hintIcon(Heroicon::InformationCircle, 'Active sale price. If set, this is the price displayed to customers.'),
                            TextInput::make('price_suffix')
                                ->maxLength(32)
                                ->placeholder('e.g. /mo, /vial')
                                ->hintIcon(Heroicon::InformationCircle, 'Optional copy appended after the price.'),
                            TextInput::make('cost')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->minValue(0)
                                ->hintIcon(Heroicon::InformationCircle, 'Internal unit cost — what the company pays. Used for reporting and P&L only; never shown on the storefront or public API.'),
                        ]),
                    Section::make('Inventory')
                        ->columnSpanFull()
                        ->components([
                            Select::make('inventory_status')
                                ->options(InventoryStatus::class)
                                ->native(false)
                                ->placeholder('Not tracked')
                                ->hintIcon(Heroicon::InformationCircle, 'When set, the In-stock flag below is derived automatically (In Stock / Back Ordered = purchasable). Leave empty to manage the flag manually.'),
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
                                ->hintIcon(Heroicon::InformationCircle, 'Assigns this product to catalog categories.')
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
                        ->components([
                            Toggle::make('is_featured')
                                ->label('Featured'),
                            Toggle::make('is_in_stock')
                                ->label('In stock')
                                ->default(true)
                                ->hintIcon(Heroicon::InformationCircle, 'Uncheck to hide this product from in-stock filters and surface an out-of-stock indicator on the frontend.'),
                            Toggle::make('requires_lab')
                                ->label('Requires lab work')
                                ->helperText('Surfaces a "Lab required" badge on the public detail page.'),
                            Toggle::make('rx_required')
                                ->label('Prescription required')
                                ->hintIcon(Heroicon::InformationCircle, 'Product requires a prescription; mirrors the provider rx_required flag on sync.'),
                            Toggle::make('is_controlled_substance')
                                ->label('Controlled substance')
                                ->hintIcon(Heroicon::InformationCircle, 'Marks the product as a controlled substance for compliance handling and reporting.'),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}
