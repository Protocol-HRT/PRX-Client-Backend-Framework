<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Services\Cms\CatalogInliner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class ProductSliderSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::ProductSlider;
    }

    public function label(): string
    {
        return 'Product slider';
    }

    public function icon(): string
    {
        return 'heroicon-o-rectangle-group';
    }

    public function description(): ?string
    {
        return 'Horizontal product carousel. Pick products by hand or let a rule (featured, newest, category) choose them.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'subhead' => null,
            'variant' => 'progressbar',
            'cta_label' => null,
            'cta_url' => null,
            'card_cta_label' => null,
            'mode' => 'manual',
            'product_ids' => [],
            'category_id' => null,
            'limit' => 8,
            'autoplay' => false,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    TextInput::make('eyebrow')->maxLength(120),
                    TextInput::make('heading')->maxLength(255),
                    Textarea::make('subhead')->rows(2)->maxLength(500),
                    TextInput::make('cta_label')
                        ->label('Section CTA label')
                        ->maxLength(120)
                        ->helperText('Optional "view all"-style link rendered with the header.'),
                    TextInput::make('cta_url')
                        ->label('Section CTA URL')
                        ->maxLength(500),
                ]),
            Section::make('Layout')
                ->components([
                    Select::make('variant')
                        ->options([
                            'progressbar' => 'Progress-bar slider (no header row)',
                            'arrows' => 'Titled slider with arrows + dots',
                        ])
                        ->default('progressbar')
                        ->required()
                        ->native(false)
                        ->helperText('Layout hint — the frontend maps each variant to a theme layout.'),
                    TextInput::make('card_cta_label')
                        ->label('Card CTA label')
                        ->maxLength(120)
                        ->helperText('Optional link text rendered on every product card (e.g. "Request Personalized Care").'),
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
                        ->default(8)
                        ->minValue(1)
                        ->maxValue(24)
                        ->visible(fn (Get $get): bool => $get('mode') !== 'manual'),
                    Toggle::make('autoplay')
                        ->helperText('Layout hint for the frontend carousel.'),
                ]),
        ];
    }

    public function resolveData(array $data): array
    {
        $data['products'] = $this->resolveProducts($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected function resolveProducts(array $data): array
    {
        $inliner = app(CatalogInliner::class);
        $mode = $data['mode'] ?? 'manual';

        if ($mode === 'manual') {
            $ids = array_map('intval', array_filter((array) ($data['product_ids'] ?? []), 'is_numeric'));

            return $inliner->productsByIds($ids);
        }

        return $inliner->productsByMode(
            $mode,
            isset($data['category_id']) && is_numeric($data['category_id']) ? (int) $data['category_id'] : null,
            (int) ($data['limit'] ?? 8),
        );
    }
}
