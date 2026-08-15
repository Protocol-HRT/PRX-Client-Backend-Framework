<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Models\Catalog\Category;
use App\Services\Cms\CatalogInliner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class CategoryGridSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::CategoryGrid;
    }

    public function label(): string
    {
        return 'Category grid';
    }

    public function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function description(): ?string
    {
        return 'Grid of catalog category cards linking to their listings. Pick categories by hand or show all visible ones.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'subhead' => null,
            'mode' => 'all',
            'category_ids' => [],
            'limit' => 12,
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
                ]),
            Section::make('Categories')
                ->components([
                    Select::make('mode')
                        ->options([
                            'all' => 'All visible categories',
                            'manual' => 'Pick categories by hand',
                        ])
                        ->default('all')
                        ->required()
                        ->reactive()
                        ->native(false),
                    Select::make('category_ids')
                        ->label('Categories')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('mode') === 'manual')
                        ->helperText('Shown in the order selected. Hidden categories are dropped automatically.'),
                    TextInput::make('limit')
                        ->numeric()
                        ->default(12)
                        ->minValue(1)
                        ->maxValue(24)
                        ->visible(fn (Get $get): bool => $get('mode') !== 'manual'),
                ]),
        ];
    }

    public function resolveData(array $data): array
    {
        $inliner = app(CatalogInliner::class);

        if (($data['mode'] ?? 'all') === 'manual') {
            $ids = array_map('intval', array_filter((array) ($data['category_ids'] ?? []), 'is_numeric'));

            $data['categories'] = $inliner->categoriesByIds($ids);

            return $data;
        }

        $data['categories'] = $inliner->visibleCategories((int) ($data['limit'] ?? 12));

        return $data;
    }
}
