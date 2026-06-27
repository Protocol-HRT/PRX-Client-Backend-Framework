<?php

namespace App\Filament\Resources\Catalog\Packages\Schemas;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Category;
use App\Models\Catalog\Tag;
use App\Models\Catalog\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Package')
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
                            ->placeholder('e.g. /mo, starting at'),
                    ]),
                Section::make('Imagery')
                    ->columns(2)
                    ->components([
                        FileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('catalog/packages')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                        FileUpload::make('banner_image_path')
                            ->label('Banner image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('catalog/packages/banners')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull()
                            ->helperText('Wide banner image for the package hero section on the frontend.'),
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
                        Toggle::make('is_featured')->label('Featured'),
                        Toggle::make('requires_lab')->label('Requires lab work'),
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
                            ->helperText('One bullet per line. Displayed as feature list on package detail page.')
                            ->schema([
                                TextInput::make('item')
                                    ->label('Highlight')
                                    ->required(),
                            ])
                            ->columnSpanFull()
                            ->reorderable()
                            ->addActionLabel('Add highlight'),
                    ]),
                Section::make('Products in this package')
                    ->components([
                        Select::make('products')
                            ->label('Products')
                            ->multiple()
                            ->relationship('products', 'name')
                            ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                            ->preload()
                            ->searchable()
                            ->helperText('Products bundled into this package.'),
                    ]),
                Section::make('Provider mapping')
                    ->description('Map this package to the matching record in the configured provider (e.g. PrescribeRx). Leave blank if not yet mapped.')
                    ->columns(2)
                    ->components([
                        TextInput::make('provider_package_id')
                            ->label('Provider package ID')
                            ->maxLength(36),
                        TextInput::make('provider_package_sku')
                            ->label('Provider SKU')
                            ->maxLength(255),
                    ]),
                Section::make('SEO overrides')
                    ->description('Leave blank to fall back to the global SEO settings.')
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_title')->maxLength(255),
                        FileUpload::make('og_image_path')
                            ->label('OG image')
                            ->image()
                            ->disk('public')
                            ->directory('catalog/packages/og')
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
