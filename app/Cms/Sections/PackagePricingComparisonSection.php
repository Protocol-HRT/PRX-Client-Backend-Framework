<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Models\Catalog\Package;
use App\Services\Cms\CatalogInliner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class PackagePricingComparisonSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::PackagePricingComparison;
    }

    public function label(): string
    {
        return 'Package pricing comparison';
    }

    public function icon(): string
    {
        return 'heroicon-o-scale';
    }

    public function description(): ?string
    {
        return 'Side-by-side comparison columns for 2–3 packages using live plan pricing from the catalog.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'subhead' => null,
            'package_ids' => [],
            'highlight_package_id' => null,
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
                    Select::make('package_ids')
                        ->label('Packages to compare')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => Package::published()->orderBy('name')->pluck('name', 'id')->all())
                        ->maxItems(3)
                        ->helperText('2–3 packages, shown in the order selected with live plan pricing.'),
                    Select::make('highlight_package_id')
                        ->label('Highlighted package')
                        ->searchable()
                        ->options(fn () => Package::published()->orderBy('name')->pluck('name', 'id')->all())
                        ->helperText('Optional. The frontend emphasizes this column (e.g. "Most popular").'),
                ]),
        ];
    }

    public function resolveData(array $data): array
    {
        $ids = array_map('intval', array_filter((array) ($data['package_ids'] ?? []), 'is_numeric'));

        $data['packages'] = app(CatalogInliner::class)->packagesByIds($ids);

        return $data;
    }
}
