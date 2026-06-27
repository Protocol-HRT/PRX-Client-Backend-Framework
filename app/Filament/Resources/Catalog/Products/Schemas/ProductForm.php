<?php

namespace App\Filament\Resources\Catalog\Products\Schemas;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Category;
use App\Models\Catalog\Tag;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product')
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
                            ->alphaDash()
                            ->helperText('URL path. Lowercase letters, numbers, hyphens.'),
                        TextInput::make('subtitle')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('short_description')
                            ->maxLength(2000)
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(8)
                            ->columnSpanFull()
                            ->helperText('Long-form description shown on the product detail page.'),
                        Select::make('status')
                            ->options(CatalogStatus::class)
                            ->default(CatalogStatus::Draft->value)
                            ->required()
                            ->native(false),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->helperText('Sort order within category listings.'),
                    ]),
                Section::make('Pricing')
                    ->description('Display pricing. Used as the actual transaction price when local checkout is the configured path; otherwise PRX is the source of truth.')
                    ->columns(3)
                    ->components([
                        TextInput::make('retail_price')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01),
                        TextInput::make('sale_price')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01),
                        TextInput::make('price_suffix')
                            ->maxLength(32)
                            ->placeholder('e.g. /mo, /vial')
                            ->helperText('Optional copy after the price.'),
                    ]),
                Section::make('Imagery')
                    ->columns(2)
                    ->components([
                        FileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('catalog/products')
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
                            ->directory('catalog/products/gallery')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->maxFiles(12)
                            ->columnSpanFull(),
                    ]),
                Section::make('Categories & tags')
                    ->columns(2)
                    ->components([
                        Select::make('category_ids')
                            ->label('Categories')
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                            ->preload()
                            ->searchable(),
                        Select::make('tag_ids')
                            ->label('Tags')
                            ->multiple()
                            ->relationship('tags', 'name')
                            ->options(fn () => Tag::query()->orderBy('name')->pluck('name', 'id'))
                            ->preload()
                            ->searchable(),
                    ]),
                Section::make('Flags')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_featured')
                            ->label('Featured'),
                        Toggle::make('requires_lab')
                            ->label('Requires lab work')
                            ->helperText('Surface a "Lab required" badge on the public detail page.'),
                    ]),
                Section::make('UI / merchandising')
                    ->columns(2)
                    ->components([
                        TextInput::make('badge_text')
                            ->label('Badge')
                            ->maxLength(32)
                            ->placeholder('e.g. Best Seller, New')
                            ->helperText('Small badge shown in listing cards. Optional.'),
                        Repeater::make('highlights')
                            ->label('Highlights')
                            ->helperText('One bullet per line. Displayed as feature list on product detail page.')
                            ->schema([
                                TextInput::make('item')
                                    ->label('Highlight')
                                    ->required(),
                            ])
                            ->columnSpanFull()
                            ->reorderable()
                            ->addActionLabel('Add highlight'),
                    ]),
                Section::make('Provider mapping')
                    ->description('Map this product to the matching record in the configured provider (e.g. PrescribeRx). Leave blank if not yet mapped.')
                    ->columns(2)
                    ->components([
                        TextInput::make('provider_product_id')
                            ->label('Provider product ID')
                            ->maxLength(36)
                            ->helperText('UUID returned by the provider /products endpoint.'),
                        TextInput::make('provider_product_sku')
                            ->label('Provider SKU')
                            ->maxLength(255)
                            ->helperText('Human-friendly identifier from the provider.'),
                    ]),
                Section::make('SEO overrides')
                    ->description('Leave blank to fall back to the global SEO settings (/admin/settings/seo).')
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_title')->maxLength(255),
                        FileUpload::make('og_image_path')
                            ->label('OG image')
                            ->image()
                            ->disk('public')
                            ->directory('catalog/products/og')
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
