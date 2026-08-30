<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Services\Cms\CatalogInliner;
use Filament\Forms\Components\Select;
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
            // Card copy. NULL, not a default string: this is operator-facing
            // wording and prx-backend ships content-free blueprints, so the
            // words belong in each install's database. The frontend omits a
            // label it is not given rather than substituting its own — the
            // whole point of moving these off the component.
            'price_intro_label' => null,
            'price_recurring_label' => null,
            'cta_label' => null,
            'cta_url' => null,
            'range_aria_label' => null,
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
            Section::make('Card copy')
                ->description('Wording on each package card. Leave a label empty to omit it — the price still shows.')
                ->components([
                    CopyFields::inline('price_intro_label')
                        ->label('Introductory price label')
                        ->helperText('Precedes the introductory price, e.g. "First month". Omitted when empty.'),
                    CopyFields::inline('price_recurring_label')
                        ->label('Recurring price label')
                        ->helperText('Precedes the recurring price, e.g. "Recurring". Omitted when empty.'),
                    // PLAIN INPUT, not CopyFields::inline(). This was the only
                    // blueprint of thirteen that made `cta_label` a rich editor,
                    // and the frontend runs the value through `toPlainText()`
                    // anyway — so any formatting an operator applied here was
                    // discarded before it reached the page. It also carried a
                    // state cast, and while the section form built every
                    // blueprint at once that cast reached the other twelve
                    // sections' `cta_label` too, turning their strings into
                    // documents. See SectionFormBuilder's class doc.
                    TextInput::make('cta_label')
                        ->label('Card button label')
                        ->maxLength(60)
                        ->helperText('Use {package} for the package name, e.g. "Start my {package}". The button is hidden when empty.'),
                    TextInput::make('cta_url')
                        ->label('Card button link')
                        ->helperText('Fallback only — each card links to its own package detail page. Used when a package has no slug.')
                        ->maxLength(2048),
                    TextInput::make('range_aria_label')
                        ->label('Scrollbar accessible name')
                        ->helperText('Describes the carousel scrubber to screen readers. Falls back to a generic name when empty.')
                        ->maxLength(255),
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
