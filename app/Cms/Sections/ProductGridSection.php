<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Same selection model as the slider (manual picks or a featured/newest/
 * category rule) — the frontend renders it as a grid instead of a carousel.
 */
class ProductGridSection extends ProductSliderSection
{
    public function type(): SectionType
    {
        return SectionType::ProductGrid;
    }

    public function label(): string
    {
        return 'Product grid';
    }

    public function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function description(): ?string
    {
        return 'Grid of product cards. Pick products by hand or let a rule (featured, newest, category) choose them.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'subhead' => null,
            'mode' => 'manual',
            'product_ids' => [],
            'category_id' => null,
            'limit' => 12,
            'columns' => '3',
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    CopyFields::inline('eyebrow'),
                    CopyFields::inline('heading'),
                    CopyFields::inline('subhead'),
                    Select::make('columns')
                        ->options(['2' => '2 columns', '3' => '3 columns', '4' => '4 columns'])
                        ->default('3')
                        ->native(false)
                        ->helperText('Layout hint for the frontend grid.'),
                ]),
            Section::make('Products')
                ->components([
                    Select::make('mode')
                        ->options([
                            'manual' => 'Pick products by hand',
                            'featured' => 'Featured products',
                            'newest' => 'Newest products',
                            'category' => 'All products in a category',
                        ])
                        ->default('manual')
                        ->required()
                        ->reactive()
                        ->native(false),
                    Select::make('product_ids')
                        ->label('Products')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('mode') === 'manual')
                        ->helperText('Shown in the order selected. Unpublished products are dropped automatically.'),
                    Select::make('category_id')
                        ->label('Category')
                        ->searchable()
                        ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('mode') === 'category'),
                    TextInput::make('limit')
                        ->numeric()
                        ->default(12)
                        ->minValue(1)
                        ->maxValue(24)
                        ->visible(fn (Get $get): bool => $get('mode') !== 'manual'),
                ]),
        ];
    }
}
