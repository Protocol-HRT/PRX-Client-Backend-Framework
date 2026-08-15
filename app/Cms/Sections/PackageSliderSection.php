<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Services\Cms\CatalogInliner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class PackageSliderSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::PackageSlider;
    }

    public function label(): string
    {
        return 'Package slider';
    }

    public function icon(): string
    {
        return 'heroicon-o-queue-list';
    }

    public function description(): ?string
    {
        return 'Horizontal carousel of package cards with live plan pricing. Pick packages by hand or let a rule (featured, newest, category) choose them.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'subhead' => null,
            'mode' => 'manual',
            'package_ids' => [],
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
                ]),
            Section::make('Packages')
                ->components([
                    Select::make('mode')
                        ->options([
                            'manual' => 'Pick packages by hand',
                            'featured' => 'Featured packages',
                            'newest' => 'Newest packages',
                            'category' => 'All packages in a category',
                        ])
                        ->default('manual')
                        ->required()
                        ->reactive()
                        ->native(false),
                    Select::make('package_ids')
                        ->label('Packages')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => Package::published()->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('mode') === 'manual')
                        ->helperText('Shown in the order selected. Unpublished packages are dropped automatically.'),
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
        $data['packages'] = $this->resolvePackages($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected function resolvePackages(array $data): array
    {
        $inliner = app(CatalogInliner::class);
        $mode = $data['mode'] ?? 'manual';

        if ($mode === 'manual') {
            $ids = array_map('intval', array_filter((array) ($data['package_ids'] ?? []), 'is_numeric'));

            return $inliner->packagesByIds($ids);
        }

        return $inliner->packagesByMode(
            $mode,
            isset($data['category_id']) && is_numeric($data['category_id']) ? (int) $data['category_id'] : null,
            (int) ($data['limit'] ?? 8),
        );
    }
}
